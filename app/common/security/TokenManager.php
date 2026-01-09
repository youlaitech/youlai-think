<?php

declare(strict_types=1);

namespace app\common\security;

interface TokenManager
{
    public function generateToken(array $userAuthInfo): AuthenticationToken;

    public function parseAccessToken(string $accessToken): array;

    public function refreshToken(string $refreshToken): AuthenticationToken;

    public function invalidate(?string $accessToken, ?string $refreshToken): void;
}
