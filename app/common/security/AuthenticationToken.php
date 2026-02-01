<?php

declare(strict_types=1);

namespace app\common\security;

final class AuthenticationToken
{
    public function __construct(
        public readonly string $tokenType,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
    ) {
    }

    public function toArray(): array
    {
        // 统一返回给前端的 token 结构
        return [
            'tokenType' => $this->tokenType,
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresIn' => $this->expiresIn,
        ];
    }
}
