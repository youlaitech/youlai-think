<?php declare(strict_types=1);

namespace app\common\sse;

/**
 * SSE 会话注册表
 * 维护SSE连接的用户会话信息，支持多设备同时登录
 */
class SseSessionRegistry
{
    /**
     * 用户连接映射
     * Key: 用户名
     * Value: 连接对象集合
     */
    private array $userEmittersMap = [];

    /**
     * 连接详情映射
     * Key: 连接对象
     * Value: 会话详情
     */
    private \SplObjectStorage $emitterUserMap;

    /**
     * 连接时间映射
     */
    private \SplObjectStorage $emitterTimeMap;

    public function __construct()
    {
        $this->emitterUserMap = new \SplObjectStorage();
        $this->emitterTimeMap = new \SplObjectStorage();
    }

    /**
     * 用户上线（建立SSE连接）
     */
    public function userConnected(string $username, SseEmitter $emitter): void
    {
        if (!isset($this->userEmittersMap[$username])) {
            $this->userEmittersMap[$username] = new \SplObjectStorage();
        }
        $this->userEmittersMap[$username]->attach($emitter);
        $this->emitterUserMap->attach($emitter, $username);
        $this->emitterTimeMap->attach($emitter, (int) (microtime(true) * 1000));
    }

    /**
     * 移除指定连接
     */
    public function removeEmitter(SseEmitter $emitter): void
    {
        if (!$this->emitterUserMap->contains($emitter)) {
            return;
        }

        $username = $this->emitterUserMap[$emitter];
        $this->emitterUserMap->detach($emitter);
        $this->emitterTimeMap->detach($emitter);

        if (isset($this->userEmittersMap[$username])) {
            $this->userEmittersMap[$username]->detach($emitter);
            if ($this->userEmittersMap[$username]->count() === 0) {
                unset($this->userEmittersMap[$username]);
            }
        }
    }

    /**
     * 获取在线用户数量
     */
    public function getOnlineUserCount(): int
    {
        return count($this->userEmittersMap);
    }

    /**
     * 获取在线连接总数
     */
    public function getTotalConnectionCount(): int
    {
        return $this->emitterUserMap->count();
    }

    /**
     * 检查用户是否在线
     */
    public function isUserOnline(string $username): bool
    {
        return isset($this->userEmittersMap[$username]) && $this->userEmittersMap[$username]->count() > 0;
    }

    /**
     * 获取所有在线用户列表
     */
    public function getOnlineUsers(): array
    {
        $result = [];
        foreach ($this->userEmittersMap as $username => $emitters) {
            $earliestLoginTime = PHP_INT_MAX;
            foreach ($emitters as $emitter) {
                if ($this->emitterTimeMap->contains($emitter)) {
                    $connectTime = $this->emitterTimeMap[$emitter];
                    if ($connectTime < $earliestLoginTime) {
                        $earliestLoginTime = $connectTime;
                    }
                }
            }

            $result[] = new OnlineUserDto(
                $username,
                $emitters->count(),
                $earliestLoginTime === PHP_INT_MAX ? (int) (microtime(true) * 1000) : $earliestLoginTime
            );
        }
        return $result;
    }

    /**
     * 获取所有连接
     */
    public function getAllEmitters(): array
    {
        $emitters = [];
        foreach ($this->emitterUserMap as $emitter) {
            $emitters[] = $emitter;
        }
        return $emitters;
    }

    /**
     * 获取指定用户的连接列表
     */
    public function getUserEmitters(string $username): ?array
    {
        if (!isset($this->userEmittersMap[$username])) {
            return null;
        }
        $emitters = [];
        foreach ($this->userEmittersMap[$username] as $emitter) {
            $emitters[] = $emitter;
        }
        return $emitters;
    }
}
