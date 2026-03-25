<?php declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use think\Request;
use think\Response;

final class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strtoupper($request->method()) === 'OPTIONS') {
            return $this->createCorsResponse();
        }

        /** @var Response $response */
        $response = $next($request);
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
