<?php declare(strict_types=1);

namespace extend\sse;

use extend\redis\RedisClient;

/**
 * SSE 事件发布器（FPM 侧写入 Redis，Workerman 侧消费）
 */
final class SseEventPublisher
{
    private static ?self $instance = null;
    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function sendDictChange(string $dictCode): void
    {
        if ($dictCode === '') return;
        $this->publish(SseTopics::DICT, ['dictCode' => $dictCode, 'timestamp' => (int)(microtime(true) * 1000)]);
    }

    public function sendSystemMessage(string $message): void
    {
        $this->publish(SseTopics::SYSTEM, ['sender' => '系统通知', 'content' => $message, 'timestamp' => (int)(microtime(true) * 1000)]);
    }

    /**
     * 向指定用户推送事件，username 为 null 时广播给所有人
     */
    public function sendToUser(?string $username, string $eventName, mixed $data): void
    {
        $this->publish($eventName, $data, $username);
    }

    private function publish(string $eventName, mixed $data, ?string $targetUser = null): void
    {
        try {
            RedisClient::get()->rpush(SseTopics::REDIS_CHANNEL, json_encode([
                'event'       => $eventName,
                'data'        => $data,
                'targetUser'  => $targetUser,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {}
    }
}
