<?php declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 跨域中间件：仅放行白名单内的来源
 */
final class Cors
{
    // 未配置 CORS_ALLOWED_ORIGINS 时仅放行本地开发前端
    private const DEFAULT_ORIGINS = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->header('origin', '');

        if (strtoupper($request->method()) === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        if ($origin === '' || !in_array($origin, $this->allowedOrigins(), true)) {
            return $response;
        }

        $response->header([
            'Access-Control-Allow-Origin' => $origin,
            'Vary' => 'Origin',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
        return $response;
    }

    private function allowedOrigins(): array
    {
        $configured = trim((string) env('CORS_ALLOWED_ORIGINS', ''));
        if ($configured === '') {
            return self::DEFAULT_ORIGINS;
        }
        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
