<?php

declare(strict_types=1);

namespace app\middleware;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;

/**
 * 演示模式写保护
 *
 * DEMO_MODE=true 时拦截写请求
 */
final class DemoProtectMiddleware
{
    /**
     * 演示模式下拦截写操作
     *
     * @param mixed    $request
     * @param \Closure $next
     *
     * @return mixed
     * @throws BusinessException 演示模式下写操作被拒绝时抛出
     */
    public function handle($request, \Closure $next)
    {
        // 演示模式下拦截写操作
        $demo = (string) (env('DEMO_MODE') ?? env('APP_DEMO') ?? 'false');
        $enabled = in_array(strtolower($demo), ['1', 'true', 'yes', 'on'], true);
        if (!$enabled) {
            return $next($request);
        }

        $method = strtoupper((string) $request->method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $path = '/' . ltrim((string) $request->pathinfo(), '/');
        if (str_starts_with($path, '/api/v1/auth/')) {
            return $next($request);
        }

        throw new BusinessException(ResultCode::DATABASE_ACCESS_DENIED);
    }
}
