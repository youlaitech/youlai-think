<?php declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 跨域中间件
 */
final class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        // 预检请求直接返回
        if (strtoupper($request->method()) === 'OPTIONS') {
            return $this->createCorsResponse();
        }

        /** @var Response $response */
        $response = $next($request);

        // 添加 CORS 头
        $response->header([
            'Access-Control-Allow-Origin' => $request->header('origin', '*'),
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);

        return $response;
    }

    private function createCorsResponse(): Response
    {
        return response('', 204)->header([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Requested-With',
            'Access-Control-Max-Age' => '86400',
        ]);
    }
}
