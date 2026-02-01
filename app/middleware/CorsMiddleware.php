<?php

declare(strict_types=1);

namespace app\middleware;

final class CorsMiddleware
{
    public function handle($request, \Closure $next)
    {
        $origin = (string) $request->header('origin');

        // 未带 Origin 时允许所有来源
        $allowOrigin = $origin !== '' ? $origin : '*';
        $allowCredentials = $allowOrigin === '*' ? 'false' : 'true';

        $headers = [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Credentials' => $allowCredentials,
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization,Content-Type,Api-Version,X-Requested-With',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];

        // 预检请求直接返回 204
        if (strtoupper((string) $request->method()) === 'OPTIONS') {
            return response('', 204)->header($headers);
        }

        $response = $next($request);
        return $response->header($headers);
    }
}
