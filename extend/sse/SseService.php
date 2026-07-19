<?php declare(strict_types=1);

namespace extend\sse;

use extend\redis\RedisClient;

/**
 * SSE 业务封装（FPM 侧调用，通过 Redis 投递消息到 Workerman）
 */
class SseService
{
    private static ?SseService $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function sendDictChange(string $dictCode): void
    {
        SseEventPublisher::getInstance()->sendDictChange($dictCode);
    }

    /**
     * 向指定用户推送，username 为 null 时广播
     */
    public function sendToUser(?string $username, string $eventName, mixed $data): void
    {
        SseEventPublisher::getInstance()->sendToUser($username, $eventName, $data);
    }

    public function sendSystemMessage(string $message): void
    {
        SseEventPublisher::getInstance()->sendSystemMessage($message);
    }

    public function broadcast(string $eventName, mixed $data): void
    {
        SseEventPublisher::getInstance()->sendToUser(null, $eventName, $data);
    }

    /**
     * 在线用户列表
     */
    public function getOnlineUsers(): array
    {
        try {
            return array_map(fn($u) => (object)['username' => $u], RedisClient::get()->hkeys(SseTopics::REDIS_ONLINE_KEY));
        } catch (\Throwable) {
            return [];
        }
    }

    public function getOnlineUserCount(): int
    {
        try {
            return (int) RedisClient::get()->hlen(SseTopics::REDIS_ONLINE_KEY);
        } catch (\Throwable) {
            return 0;
        }
    }
}
