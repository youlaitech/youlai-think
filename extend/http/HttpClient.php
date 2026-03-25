<?php declare(strict_types=1);

namespace extend\http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use think\facade\Log;

final class HttpClient
{
    protected Client $client;

    protected array $config = [
        'timeout' => 30,
        'connect_timeout' => 5,
        'verify' => false,
        'http_errors' => true,
        'headers' => [
            'User-Agent' => 'YoulaiThink/1.0',
            'Accept' => 'application/json',
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->client = new Client($this->config);
    }

    public function get(string $url, array $params = [], array $headers = []): array
    {
        return $this->request('GET', $url, ['query' => $params, 'headers' => $headers]);
    }

    public function post(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $url, ['json' => $data, 'headers' => $headers]);
    }

    public function postForm(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $url, ['form_params' => $data, 'headers' => $headers]);
    }

    public function put(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $url, ['json' => $data, 'headers' => $headers]);
    }

    public function delete(string $url, array $params = [], array $headers = []): array
    {
        return $this->request('DELETE', $url, ['query' => $params, 'headers' => $headers]);
    }

    public function upload(string $url, string $filePath, string $fieldName = 'file', array $extraData = []): array
    {
        $multipart = [['name' => $fieldName, 'contents' => fopen($filePath, 'r'), 'filename' => basename($filePath)]];
        foreach ($extraData as $name => $contents) {
            $multipart[] = compact('name', 'contents');
        }
        return $this->request('POST', $url, ['multipart' => $multipart]);
    }

    public function download(string $url, string $savePath): bool
    {
        try {
            return $this->client->get($url, ['sink' => $savePath])->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            Log::error('文件下载失败', ['url' => $url, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function request(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $url, $options);
            $data = json_decode($response->getBody()->getContents(), true) ?: $response->getBody()->getContents();
            return ['success' => true, 'status' => $response->getStatusCode(), 'data' => $data, 'headers' => $response->getHeaders()];
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $body = $response ? $response->getBody()->getContents() : '';
            Log::error('HTTP请求失败', ['method' => $method, 'url' => $url, 'status' => $response?->getStatusCode(), 'error' => $e->getMessage(), 'body' => $body]);
            return ['success' => false, 'status' => $response?->getStatusCode() ?? 0, 'error' => $e->getMessage(), 'body' => $body];
        } catch (GuzzleException $e) {
            Log::error('HTTP请求异常', ['method' => $method, 'url' => $url, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getClient(): Client { return $this->client; }
    public function setBaseUrl(string $baseUrl): self { $this->config['base_uri'] = $baseUrl; $this->client = new Client($this->config); return $this; }
    public function setTimeout(int $timeout): self { $this->config['timeout'] = $timeout; $this->client = new Client($this->config); return $this; }
    public function setHeaders(array $headers): self { $this->config['headers'] = array_merge($this->config['headers'], $headers); $this->client = new Client($this->config); return $this; }
}
