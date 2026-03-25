<?php declare(strict_types=1);

namespace app\common\middleware;

use app\common\exception\BusinessException;
use extend\jwt\TokenManagerResolver;
use app\common\web\ResultCode;
use think\Response;

final class AuthMiddleware
{
    public function handle($request, \Closure $next): Response
    {
        if (strtoupper((string) $request->method()) === 'OPTIONS') {
            $response = $next($request);
            return $response instanceof Response ? $response : response($response);
        }

        $path = '/' . ltrim((string) $request->pathinfo(), '/');
        if (str_starts_with($path, '/api/v1/auth/')) {
            $response = $next($request);
            return $response instanceof Response ? $response : response($response);
        }

        $headerName = (string) config('security.token_header');
        $tokenPrefix = (string) config('security.token_prefix');

        $raw = (string) $request->header($headerName);
        if ($raw === '') {
            $raw = (string) $request->header(strtolower($headerName));
        }
        if ($raw === '') throw new BusinessException(ResultCode::ACCESS_UNAUTHORIZED);

        $token = $raw;
        if ($tokenPrefix !== '' && str_starts_with($raw, $tokenPrefix)) {
            $token = substr($raw, strlen($tokenPrefix));
        }

        $token = trim((string) $token);
        if ($token === '') throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);

        $request->__authUser = TokenManagerResolver::resolve()->parseAccessToken($token);

        $response = $next($request);
        return $response instanceof Response ? $response : response($response);
    }
}
