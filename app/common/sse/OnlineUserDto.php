<?php declare(strict_types=1);

namespace app\common\sse;

/**
 * 在线用户信息DTO
 */
class OnlineUserDto
{
    public function __construct(
        public string $username,
        public int $sessionCount,
        public int $loginTime
    ) {}

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'sessionCount' => $this->sessionCount,
            'loginTime' => $this->loginTime,
        ];
    }
}
