<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

/**
 * API 冒烟测试。
 *
 * @author Ray.Hao
 * @version 0.0.1
 */
final class ApiSmokeTest extends TestCase
{
    private static string $baseUrl;
    private static string $username;
    private static string $password;
    private static string $accessToken;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = rtrim((string) (getenv('BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
        self::$username = (string) (getenv('USERNAME') ?: 'admin');
        self::$password = (string) (getenv('PASSWORD') ?: '123456');

        $payload = self::postJson('/api/v1/auth/login', [
            'username' => self::$username,
            'password' => self::$password,
        ]);

        self::assertResultOk($payload);

        $data = $payload['data'] ?? null;
        self::assertIsArray($data);

        $token = (string) ($data['accessToken'] ?? '');
        self::assertNotSame('', $token);
        self::$accessToken = $token;
    }

    /**
     * /api/v1/menus/routes
     */
    public function testMenusRoutes(): void
    {
        $payload = self::get('/api/v1/menus/routes');
        self::assertResultOk($payload);
        self::assertIsArray($payload['data']);
    }

    /**
     * /api/v1/users
     */
    public function testUsersPage(): void
    {
        $payload = self::get('/api/v1/users?pageNum=1&pageSize=10');
        self::assertResultOk($payload);
        self::assertIsArray($payload['data']);
        self::assertArrayHasKey('page', $payload);
        self::assertIsArray($payload['page']);
        self::assertArrayHasKey('pageNum', $payload['page']);
        self::assertArrayHasKey('pageSize', $payload['page']);
        self::assertArrayHasKey('total', $payload['page']);
    }

    /**
     * /api/v1/logs
     */
    public function testLogsPage(): void
    {
        $payload = self::get('/api/v1/logs?pageNum=1&pageSize=10');
        self::assertResultOk($payload);
        self::assertIsArray($payload['data']);
        self::assertArrayHasKey('page', $payload);
        self::assertIsArray($payload['page']);
        self::assertArrayHasKey('pageNum', $payload['page']);
        self::assertArrayHasKey('pageSize', $payload['page']);
        self::assertArrayHasKey('total', $payload['page']);
    }

    /**
     * /api/v1/statistics/visits/overview
     */
    public function testVisitOverview(): void
    {
        $payload = self::get('/api/v1/statistics/visits/overview');
        self::assertResultOk($payload);
        self::assertIsArray($payload['data']);
    }

    /**
     * /api/v1/statistics/visits/trend
     */
    public function testVisitTrend(): void
    {
        $startDate = date('Y-m-d', strtotime('-6 day'));
        $endDate = date('Y-m-d');

        $payload = self::get('/api/v1/statistics/visits/trend?startDate=' . rawurlencode($startDate) . '&endDate=' . rawurlencode($endDate));
        self::assertResultOk($payload);
        self::assertIsArray($payload['data']);
    }

    private static function get(string $path): array
    {
        return self::request('GET', $path, null, true);
    }

    private static function postJson(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        self::assertNotFalse($body);

        return self::request('POST', $path, $body, false, ['Content-Type: application/json']);
    }

    private static function request(string $method, string $path, ?string $body, bool $auth, array $headers = []): array
    {
        $url = self::$baseUrl . $path;

        $ch = curl_init();
        self::assertNotFalse($ch);

        $method = strtoupper($method);
        $headers = array_values($headers);
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . self::$accessToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $resp = curl_exec($ch);
        self::assertNotFalse($resp);

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        self::assertSame(200, $status, 'HTTP status not 200 for ' . $method . ' ' . $path);

        $decoded = json_decode((string) $resp, true);
        self::assertIsArray($decoded, 'Invalid JSON for ' . $method . ' ' . $path . ': ' . (string) $resp);

        return $decoded;
    }

    private static function assertResultOk(array $payload): void
    {
        self::assertArrayHasKey('code', $payload);
        self::assertArrayHasKey('msg', $payload);
        self::assertSame('00000', (string) $payload['code'], 'code not success: ' . (string) ($payload['msg'] ?? ''));
        self::assertArrayHasKey('data', $payload);
    }
}
