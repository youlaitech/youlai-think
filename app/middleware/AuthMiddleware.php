<?php

declare(strict_types=1);

namespace app\middleware;

use app\common\exception\BusinessException;
use app\common\security\TokenManagerResolver;
use app\common\web\ResultCode;

final class AuthMiddleware
{
    public function handle($request, \Closure $next)
    {
        if (strtoupper((string) $request->method()) === 'OPTIONS') {
            return $next($request);
        }

        $path = '/' . ltrim((string) $request->pathinfo(), '/');

        if (str_starts_with($path, '/api/v1/auth/')) {
            return $next($request);
        }

        $headerName = (string) config('security.token_header');
        $tokenPrefix = (string) config('security.token_prefix');

        $raw = (string) $request->header($headerName);
        if ($raw === '') {
            $raw = (string) $request->header(strtolower($headerName));
        }

        if ($raw === '') {
            throw new BusinessException(ResultCode::ACCESS_UNAUTHORIZED);
        }

        $token = $raw;
        if ($tokenPrefix !== '' && str_starts_with($raw, $tokenPrefix)) {
            $token = substr($raw, strlen($tokenPrefix));
        }

        $token = trim((string) $token);
        if ($token === '') {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $user = (new TokenManagerResolver())->get()->parseAccessToken($token);

        if ($request instanceof \app\Request) {
            $request->setAuthUser($user);
        }

        return $next($request);
    }
}
