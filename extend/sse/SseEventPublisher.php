<?php declare(strict_types=1);

namespace extend\sse;

use extend\redis\RedisClient;

/**
 * SSE 事件发布器（Redis Pub/Sub 模式）
 */
final class SseEventPublisher
{
    private const REDIS_SSE_CHANNEL = 'sse:broadcast';
    private static ?self $instance = null;
    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function sendDictChange(string $dictCode): void
    {
        if ($dictCode === '') return;
        $this->publish(SseTopics::DICT, ['dictCode' => $dictCode, 'timestamp' => (int)(microtime(true) * 1000)]);
    }

    public function sendOnlineCount(int $count): void { $this->publish(SseTopics::ONLINE_COUNT, $count); }

    public function sendSystemMessage(string $message): void
    {
        $this->publish(SseTopics::SYSTEM, ['sender' => '系统通知', 'content' => $message, 'timestamp' => (int)(microtime(true) * 1000)]);
    }

    public function sendToUser(string $username, string $eventName, mixed $data): void { $this->publish($eventName, $data, $username); }

    private function publish(string $eventName, mixed $data, ?string $targetUser = null): void
    {
        try {
            $redis = RedisClient::get();
            $redis->rpush(self::REDIS_SSE_CHANNEL, json_encode(['event' => $eventName, 'data' => $data, 'targetUser' => $targetUser], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {}
    }
}
