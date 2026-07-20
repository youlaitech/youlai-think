<?php declare(strict_types=1);

namespace app\file\storage;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use app\common\exception\BusinessException;

/**
 * MinIO / S3 兼容对象存储（参考 youlai-boot 的 MinioFileServiceImpl）。
 * 直接调用 S3 兼容 REST API（AWS Signature V4），无需安装 AWS SDK。
 */
final class MinioStorage
{
    private string $endpoint;
    private string $accessKey;
    private string $secretKey;
    private string $bucket;
    private string $domain;
    private string $region;
    private Client $http;

    public function __construct(array $config)
    {
        $this->endpoint  = rtrim((string) ($config['endpoint'] ?? ''), '/');
        $this->accessKey = (string) ($config['access-key'] ?? '');
        $this->secretKey = (string) ($config['secret-key'] ?? '');
        $this->bucket    = (string) ($config['bucket'] ?? '');
        $this->domain    = (string) ($config['domain'] ?? '');
        $this->region    = (string) ($config['region'] ?? 'us-east-1');
        $this->http      = new Client(['timeout' => 30]);
    }

    /**
     * 上传对象，返回完整可访问 URL。
     * @param string $key 对象键，如 20260719/xxxx.jpg
     * @param resource|string $body 文件内容（资源或字符串）
     */
    public function upload(string $key, $body, ?string $contentType = null): string
    {
        $this->ensureBucket();

        $bodyStr   = is_resource($body) ? stream_get_contents($body) : (string) $body;
        $payloadHash = hash('sha256', $bodyStr);
        $headers = [
            'Content-Type'        => $contentType ?: 'application/octet-stream',
            'X-Amz-Content-Sha256' => $payloadHash,
        ];

        $path    = '/' . $this->bucket . '/' . $key;
        $signed  = $this->sign('PUT', $path, '', $headers, $payloadHash);
        $url     = $this->endpoint . $path;

        $resp = $this->http->put($url, ['headers' => $signed, 'body' => $bodyStr]);
        if ($resp->getStatusCode() >= 300) {
            throw new BusinessException('文件上传到 MinIO 失败: HTTP ' . $resp->getStatusCode());
        }

        return $this->buildUrl($key);
    }

    /**
     * 删除对象。
     */
    public function delete(string $key): void
    {
        $payloadHash = hash('sha256', '');
        $headers = ['X-Amz-Content-Sha256' => $payloadHash];

        $path   = '/' . $this->bucket . '/' . $key;
        $signed = $this->sign('DELETE', $path, '', $headers, $payloadHash);
        $url    = $this->endpoint . $path;

        try {
            $this->http->delete($url, ['headers' => $signed]);
        } catch (RequestException) {
            // 对象不存在等情况忽略
        }
    }

    /**
     * 从完整 URL 中提取对象 key（与 youlai-boot 的 deleteFile 对应）。
     */
    public function extractKey(string $filePath): string
    {
        $base   = $this->domain !== '' ? $this->domain : $this->endpoint;
        $prefix = rtrim($base, '/') . '/' . $this->bucket . '/';
        if (str_starts_with($filePath, $prefix)) {
            return substr($filePath, strlen($prefix));
        }
        // 兜底：去掉协议与 host、bucket 前缀
        $p = $filePath;
        $p = preg_replace('#^https?://#', '', $p);
        $p = preg_replace('#^[^/]+/' . preg_quote($this->bucket, '#') . '/#', '', $p);
        return ltrim($p, '/');
    }

    /**
     * 返回完整可访问 URL（domain 优先，否则用 endpoint）。
     */
    private function buildUrl(string $key): string
    {
        $base = $this->domain !== '' ? $this->domain : $this->endpoint;
        return rtrim($base, '/') . '/' . $this->bucket . '/' . $key;
    }

    /**
     * 确保 bucket 存在；不存在则创建并设置为公开读。
     */
    private function ensureBucket(): void
    {
        // 先探测 bucket 是否已存在
        try {
            $payloadHash = hash('sha256', '');
            $headers = ['X-Amz-Content-Sha256' => $payloadHash];
            $signed  = $this->sign('HEAD', '/' . $this->bucket, '', $headers, $payloadHash);
            $resp = $this->http->head($this->endpoint . '/' . $this->bucket, ['headers' => $signed]);
            if ($resp->getStatusCode() < 400) {
                return;
            }
        } catch (RequestException) {
            // 继续尝试创建
        }

        // 创建 bucket
        $payloadHash = hash('sha256', '');
        $headers = ['X-Amz-Content-Sha256' => $payloadHash];
        $signed  = $this->sign('PUT', '/' . $this->bucket, '', $headers, $payloadHash);
        try {
            $this->http->put($this->endpoint . '/' . $this->bucket, ['headers' => $signed, 'body' => '']);
        } catch (RequestException) {
            // bucket 可能已存在，忽略
        }

        // 设置公开读策略（idempotent）
        $policy = $this->publicPolicy();
        $policyHash = hash('sha256', $policy);
        $policyHeaders = [
            'Content-Type'         => 'application/json',
            'X-Amz-Content-Sha256' => $policyHash,
        ];
        $signed = $this->sign('PUT', '/' . $this->bucket, 'policy', $policyHeaders, $policyHash);
        try {
            $this->http->put($this->endpoint . '/' . $this->bucket . '?policy', [
                'headers' => $signed,
                'body'    => $policy,
            ]);
        } catch (RequestException) {
            // 无权限设置策略时忽略（上传仍可能成功）
        }
    }

    private function publicPolicy(): string
    {
        $bucket = $this->bucket;
        return (string) json_encode([
            'Version'   => '2012-10-17',
            'Statement' => [
                [
                    'Effect'    => 'Allow',
                    'Principal' => ['AWS' => ['*']],
                    'Action'    => ['s3:GetObject', 's3:PutObject', 's3:DeleteObject'],
                    'Resource'  => ["arn:aws:s3:::$bucket/*"],
                ],
            ],
        ]);
    }

    /**
     * 生成 AWS Signature V4 签名头。
     */
    private function sign(string $method, string $path, string $query, array $headers, string $payloadHash): array
    {
        $amzDate   = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $port = parse_url($this->endpoint, PHP_URL_PORT);
        $hostHeader = $port ? $host . ':' . $port : $host;

        $headers = array_change_key_case($headers, CASE_LOWER);
        $headers['host'] = $hostHeader;
        $headers['x-amz-date'] = $amzDate;
        ksort($headers);

        $signedHeadersList = array_keys($headers);
        $signedHeaders = implode(';', $signedHeadersList);

        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim((string) $v) . "\n";
        }

        $canonicalQuery = $this->canonicalQuery($query);

        $canonicalRequest = $method . "\n"
            . $path . "\n"
            . $canonicalQuery . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;

        $scope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $headers['Authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey
            . '/' . $scope . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        return $headers;
    }

    private function canonicalQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }
        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            if (str_contains($pair, '=')) {
                [$k, $v] = explode('=', $pair, 2);
            } else {
                $k = $pair;
                $v = '';
            }
            $pairs[] = rawurlencode(urldecode($k)) . '=' . rawurlencode(urldecode($v));
        }
        sort($pairs);
        return implode('&', $pairs);
    }
}
