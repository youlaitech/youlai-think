<?php

declare(strict_types=1);

namespace app\common\security;

interface TokenManager
{
    // 生成登录令牌
    public function generateToken(array $userAuthInfo): AuthenticationToken;

    // 解析 access token 并返回用户信息
    public function parseAccessToken(string $accessToken): array;

    // 刷新 access/refresh token
    public function refreshToken(string $refreshToken): AuthenticationToken;

    // 主动失效 token
    public function invalidate(?string $accessToken, ?string $refreshToken): void;
}
