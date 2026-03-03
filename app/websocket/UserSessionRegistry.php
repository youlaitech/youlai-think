<?php declare(strict_types=1);

namespace app\websocket;

/**
 * 用户会话注册表
 * 管理在线用户和会话状态
 */
class UserSessionRegistry
{
    /** @var array<string, array<string>> 用户名 => 会话ID列表 */
    protected array $userSessions = [];

    /** @var array<string, string> 会话ID => 用户名 */
    protected array $sessionUser = [];

    /** @var array<string, array<string>> 会话ID => 订阅列表 */
    protected array $sessionSubscriptions = [];

    /**
     * 用户连接
     */
    public function userConnected(string $username, string $sessionId): void
    {
        if (!isset($this->userSessions[$username])) {
            $this->userSessions[$username] = [];
        }
        $this->userSessions[$username][] = $sessionId;
        $this->sessionUser[$sessionId] = $username;
        $this->sessionSubscriptions[$sessionId] = [];
    }

    /**
     * 用户断开连接
     */
    public function userDisconnected(string $sessionId): ?string
    {
        $username = $this->sessionUser[$sessionId] ?? null;
        if ($username === null) {
            return null;
        }

        // 移除会话
        unset($this->sessionUser[$sessionId]);
        unset($this->sessionSubscriptions[$sessionId]);

        // 从用户会话列表中移除
        if (isset($this->userSessions[$username])) {
            $this->userSessions[$username] = array_values(
                array_filter($this->userSessions[$username], fn($sid) => $sid !== $sessionId)
            );
            if (empty($this->userSessions[$username])) {
                unset($this->userSessions[$username]);
            }
        }

        return $username;
    }

    /**
     * 添加订阅
     */
    public function addSubscription(string $sessionId, string $destination, string $subscriptionId): void
    {
        if (!isset($this->sessionSubscriptions[$sessionId])) {
            $this->sessionSubscriptions[$sessionId] = [];
        }
        $this->sessionSubscriptions[$sessionId][$subscriptionId] = $destination;
    }

    /**
     * 移除订阅
     */
    public function removeSubscription(string $sessionId, string $subscriptionId): void
    {
        unset($this->sessionSubscriptions[$sessionId][$subscriptionId]);
    }

    /**
     * 获取会话的所有订阅
     */
    public function getSubscriptions(string $sessionId): array
    {
        return $this->sessionSubscriptions[$sessionId] ?? [];
    }

    /**
     * 获取订阅了指定目标的所有会话
     */
    public function getSessionsByDestination(string $destination): array
    {
        $sessions = [];
        foreach ($this->sessionSubscriptions as $sessionId => $subscriptions) {
            if (in_array($destination, $subscriptions, true)) {
                $sessions[] = $sessionId;
            }
        }
        return $sessions;
    }

    /**
     * 获取用户的所有会话
     */
    public function getUserSessions(string $username): array
    {
        return $this->userSessions[$username] ?? [];
    }

    /**
     * 获取会话对应的用户名
     */
    public function getUsername(string $sessionId): ?string
    {
        return $this->sessionUser[$sessionId] ?? null;
    }

    /**
     * 获取在线用户数
     */
    public function getOnlineUserCount(): int
    {
        return count($this->userSessions);
    }

    /**
     * 获取在线用户列表
     */
    public function getOnlineUsers(): array
    {
        return array_keys($this->userSessions);
    }

    /**
     * 检查用户是否在线
     */
    public function isUserOnline(string $username): bool
    {
        return isset($this->userSessions[$username]) && !empty($this->userSessions[$username]);
    }

    /**
     * 获取总会话数
     */
    public function getTotalSessionCount(): int
    {
        return count($this->sessionUser);
    }
}
