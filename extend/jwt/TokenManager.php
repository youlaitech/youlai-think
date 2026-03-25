<?php declare(strict_types=1);

namespace extend\jwt;

interface TokenManager
{
    public function generateTokenPair(int $userId, array $extra = []): AuthenticationToken;
    public function validateAccessToken(string $token): array;
    public function parseAccessToken(string $token): array;
    public function validateRefreshToken(string $token): array;
    public function blacklist(string $token): void;
    public function revokeUserTokens(int $userId): void;
    public function storeRefreshToken(int $userId, string $refreshToken): void;
    public function rotateRefreshToken(int $userId, string $oldRefreshToken): AuthenticationToken;
}
