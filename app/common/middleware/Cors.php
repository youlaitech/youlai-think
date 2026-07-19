<?php declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 跨域中间件，自动加 CORS 响应头
 */
final class Cors
{
    /**
     * 处理跨域预检和正常请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('origin', '');

        if (strtoupper($request->method()) === 'OPTIONS') {
            return $this->createCorsResponse($origin);
        }

        /** @var Response $response */
        $response = $next($request);
        $response->header([
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
        return $response;
    }

    /**
     * 返回预检响应
     */
    private function createCorsResponse(string $origin): Response
    {
        return response('', 204)->header([
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,PATCH,OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type,Authorization,X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400',
        ]);
    }
}
