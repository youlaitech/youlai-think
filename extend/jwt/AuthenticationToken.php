<?php declare(strict_types=1);

namespace extend\jwt;

readonly class AuthenticationToken
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $accessTokenTtl,
        public int $refreshTokenTtl,
    ) {}
}
