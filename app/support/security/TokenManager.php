<?php declare(strict_types=1);

namespace app\support\security;

interface TokenManager
{
    // 鐢熸垚鐧诲綍浠ょ墝
    public function generateToken(array $userAuthInfo): AuthenticationToken;

    // 瑙ｆ瀽 access token 骞惰繑鍥炵敤鎴蜂俊鎭?    public function parseAccessToken(string $accessToken): array;

    // 鍒锋柊 access/refresh token
    public function refreshToken(string $refreshToken): AuthenticationToken;

    // 涓诲姩澶辨晥 token
    public function invalidate(?string $accessToken, ?string $refreshToken): void;
}
