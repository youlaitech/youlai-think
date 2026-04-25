<?php declare(strict_types=1);

namespace extend\sse;

use extend\redis\RedisClient;

/**
 * SSE 业务封装层
 * 对外提供字典变更、系统消息、广播等快捷方法
 */
class SseService
{
    private const REDIS_ONLINE_KEY = 'sse:online_users';
    private static ?SseService $instance = null;

    public function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function sendDictChange(string $dictCode): void { SseEventPublisher::getInstance()->sendDictChange($dictCode); }
    public function sendToUser(string $username, string $eventName, mixed $data): void { SseEventPublisher::getInstance()->sendToUser($username, $eventName, $data); }
    public function sendSystemMessage(string $message): void { SseEventPublisher::getInstance()->sendSystemMessage($message); }
    public function broadcast(string $eventName, mixed $data): void { SseEventPublisher::getInstance()->sendToUser('', $eventName, $data); }

    public function getOnlineUsers(): array
    {
        try { $redis = RedisClient::get(); return array_map(fn($u) => (object)['username' => $u], $redis->hkeys(self::REDIS_ONLINE_KEY)); }
        catch (\Throwable) { return []; }
    }

    public function getOnlineUserCount(): int
    {
        try { return (int)RedisClient::get()->hlen(self::REDIS_ONLINE_KEY); }
        catch (\Throwable) { return 0; }
    }

    public function createConnection(string $username, SseEmitter $emitter): void {}
    public function sendOnlineCount(): void {}
    public function removeEmitter(SseEmitter $emitter): void {}
}
