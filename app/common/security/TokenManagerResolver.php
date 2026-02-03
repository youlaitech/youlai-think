<?php

declare(strict_types=1);

namespace app\common\security;

final class TokenManagerResolver
{
    private ?TokenManager $manager = null;

    public function get(): TokenManager
    {
        if ($this->manager !== null) {
            return $this->manager;
        }

        $mode = (string) config('security.session_mode');

        // 按配置选择会话实现
        $this->manager = match ($mode) {
            'redis-token' => new RedisTokenManager(),
            default => new JwtTokenManager(),
        };

        return $this->manager;
    }
}
