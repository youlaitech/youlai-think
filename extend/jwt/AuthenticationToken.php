<?php declare(strict_types=1);

namespace extend\jwt;

/**
 * 登录成功后返回的 Token 数据对象
 */
readonly class AuthenticationToken
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $accessTokenTtl,
        public int $refreshTokenTtl,
    ) {}
}
