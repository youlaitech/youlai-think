<?php

declare(strict_types=1);

namespace app\common\websocket;

/**
 * 在线用户信息DTO
 * 用于返回在线用户的基本信息，包括用户名、会话数量和登录时间。
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

/**
 * WebSocket 用户会话注册表
 * 维护WebSocket连接的用户会话信息，支持多设备同时登录。
 * 采用双数组结构实现高效查询。
 */
class UserSessionRegistry
{
    /**
     * 用户会话映射表
     * Key: 用户名
     * Value: 会话ID集合（支持多设备登录）
     */
    private array $userSessionsMap = [];

    /**
     * 会话详情映射表
     * Key: 会话ID
     * Value: 会话详情
     */
    private array $sessionDetailsMap = [];

    /**
     * 用户上线（建立WebSocket连接）
     */
    public function userConnected(string $username, string $sessionId): void
    {
        if (!isset($this->userSessionsMap[$username])) {
            $this->userSessionsMap[$username] = [];
        }
        $this->userSessionsMap[$username][$sessionId] = true;
        $this->sessionDetailsMap[$sessionId] = [
            'username' => $username,
            'sessionId' => $sessionId,
            'connectTime' => (int) (microtime(true) * 1000),
        ];
    }

    /**
     * 用户下线（断开所有WebSocket连接）
     * 移除该用户的所有会话信息
     */
    public function userDisconnected(string $username): void
    {
        if (isset($this->userSessionsMap[$username])) {
            foreach (array_keys($this->userSessionsMap[$username]) as $sessionId) {
                unset($this->sessionDetailsMap[$sessionId]);
            }
            unset($this->userSessionsMap[$username]);
        }
    }

    /**
     * 移除指定会话（单设备下线）
     * 当用户某一设备断开连接时调用，保留其他设备的会话
     */
    public function removeSession(string $sessionId): void
    {
        if (!isset($this->sessionDetailsMap[$sessionId])) {
            return;
        }

        $username = $this->sessionDetailsMap[$sessionId]['username'];
        unset($this->sessionDetailsMap[$sessionId]);

        if (isset($this->userSessionsMap[$username])) {
            unset($this->userSessionsMap[$username][$sessionId]);
            if (empty($this->userSessionsMap[$username])) {
                unset($this->userSessionsMap[$username]);
            }
        }
    }

    /**
     * 获取在线用户数量
     */
    public function getOnlineUserCount(): int
    {
        return count($this->userSessionsMap);
    }

    /**
     * 获取指定用户的会话数量
     */
    public function getUserSessionCount(string $username): int
    {
        return isset($this->userSessionsMap[$username]) ? count($this->userSessionsMap[$username]) : 0;
    }

    /**
     * 获取在线会话总数
     */
    public function getTotalSessionCount(): int
    {
        return count($this->sessionDetailsMap);
    }

    /**
     * 检查用户是否在线
     */
    public function isUserOnline(string $username): bool
    {
        return isset($this->userSessionsMap[$username]) && !empty($this->userSessionsMap[$username]);
    }

    /**
     * 获取所有在线用户列表
     */
    public function getOnlineUsers(): array
    {
        $result = [];
        foreach ($this->userSessionsMap as $username => $sessions) {
            $earliestLoginTime = PHP_INT_MAX;
            foreach (array_keys($sessions) as $sessionId) {
                if (isset($this->sessionDetailsMap[$sessionId]['connectTime'])) {
                    $connectTime = $this->sessionDetailsMap[$sessionId]['connectTime'];
                    if ($connectTime < $earliestLoginTime) {
                        $earliestLoginTime = $connectTime;
                    }
                }
            }

            $result[] = new OnlineUserDto(
                $username,
                count($sessions),
                $earliestLoginTime === PHP_INT_MAX ? (int) (microtime(true) * 1000) : $earliestLoginTime
            );
        }
        return $result;
    }
}
