<?php declare(strict_types=1);

namespace app\file\storage;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use app\common\exception\BusinessException;

/**
 * 阿里云 OSS 对象存储（参考 youlai-boot 的 AliyunFileServiceImpl）。
 * 直接调用 OSS REST API + 经典 V1 签名（HMAC-SHA1），无需安装官方 SDK。
 */
final class AliyunStorage
{
    private string $endpoint;
    private string $accessKeyId;
    private string $accessKeySecret;
    private string $bucket;
    private string $domain;
    private Client $http;

    public function __construct(array $config)
    {
        $this->endpoint         = rtrim((string) ($config['endpoint'] ?? ''), '/');
        $this->accessKeyId     = (string) ($config['access-key-id'] ?? '');
        $this->accessKeySecret = (string) ($config['access-key-secret'] ?? '');
        $this->bucket          = (string) ($config['bucket'] ?? '');
        $this->domain          = (string) ($config['domain'] ?? '');
        $this->http            = new Client(['timeout' => 30]);
    }

    /** 上传对象，返回完整可访问 URL */
    public function upload(string $key, $body, ?string $contentType = null): string
    {
        $bodyStr = is_resource($body) ? stream_get_contents($body) : (string) $body;
        $date    = gmdate('D, d M Y H:i:s \G\M\T');
        $headers = [
            'Content-Type' => $contentType ?: 'application/octet-stream',
            'Date'         => $date,
        ];
        $signed  = $this->sign('PUT', $key, $headers);
        $url     = $this->objectUrl($key);

        $resp = $this->http->put($url, ['headers' => $signed, 'body' => $bodyStr]);
        if ($resp->getStatusCode() >= 300) {
            throw new BusinessException('文件上传到阿里云 OSS 失败: HTTP ' . $resp->getStatusCode());
        }

        return $this->buildUrl($key);
    }

    /** 删除对象 */
    public function delete(string $key): void
    {
        $date    = gmdate('D, d M Y H:i:s \G\M\T');
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Date'         => $date,
        ];
        $signed  = $this->sign('DELETE', $key, $headers);
        $url     = $this->objectUrl($key);

        try {
            $this->http->delete($url, ['headers' => $signed]);
        } catch (RequestException) {
            // 对象不存在等情况忽略
        }
    }

    /** 从完整 URL 中提取对象 key */
    public function extractKey(string $filePath): string
    {
        $base   = $this->domain !== ''
            ? rtrim($this->domain, '/')
            : ('https://' . $this->bucket . '.' . $this->plainEndpoint());
        $prefix = $base . '/' . $this->bucket . '/';
        if (str_starts_with($filePath, $prefix)) {
            return substr($filePath, strlen($prefix));
        }
        // 兜底：按 bucket 前缀截取
        $p = preg_replace('#^https?://#', '', $filePath);
        $p = preg_replace('#^[^/]+/' . preg_quote($this->bucket, '#') . '/#', '', $p);
        return ltrim($p, '/');
    }

    /** 完整可访问 URL（domain 优先，否则 https://{bucket}.{endpoint}） */
    private function buildUrl(string $key): string
    {
        $base = $this->domain !== ''
            ? rtrim($this->domain, '/')
            : ('https://' . $this->bucket . '.' . $this->plainEndpoint());
        return $base . '/' . $key;
    }

    /** 对象访问地址（用于 REST 请求） */
    private function objectUrl(string $key): string
    {
        $host = $this->domain !== ''
            ? rtrim($this->domain, '/')
            : ('https://' . $this->bucket . '.' . $this->plainEndpoint());
        return $host . '/' . $key;
    }

    /** 去掉协议头的纯 endpoint，如 oss-cn-hangzhou.aliyuncs.com */
    private function plainEndpoint(): string
    {
        $e = $this->endpoint;
        if (str_starts_with($e, 'http://'))       $e = substr($e, 7);
        elseif (str_starts_with($e, 'https://')) $e = substr($e, 8);
        return rtrim($e, '/');
    }

    /** 生成 OSS 经典 V1 签名（HMAC-SHA1） */
    private function sign(string $method, string $key, array $headers): array
    {
        // StringToSign = VERB\n + Content-MD5(\n) + Content-Type\n + Date\n + /{bucket}/{key}
        $resource     = '/' . $this->bucket . '/' . $key;
        $contentType  = $headers['Content-Type'] ?? '';
        $date         = $headers['Date'] ?? '';
        $stringToSign = $method . "\n\n" . $contentType . "\n" . $date . "\n" . $resource;
        $signature    = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));
        $headers['Authorization'] = 'OSS ' . $this->accessKeyId . ':' . $signature;
        return $headers;
    }
}
