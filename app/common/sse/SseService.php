<?php declare(strict_types=1);

namespace app\common\sse;

/**
 * SSE 服务类
 * 处理SSE连接、事件发送和广播
 */
class SseService
{
    private SseSessionRegistry $registry;

    private static ?SseService $instance = null;

    public function __construct()
    {
        $this->registry = new SseSessionRegistry();
    }

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 创建SSE连接
     */
    public function createConnection(string $username, SseEmitter $emitter): void
    {
        $this->registry->userConnected($username, $emitter);
        $this->sendOnlineCount();
    }

    /**
     * 发送字典变更事件
     */
    public function sendDictChange(string $dictCode): void
    {
        if (empty($dictCode)) {
            return;
        }
        $event = new DictChangeEvent($dictCode);
        $this->broadcast(SseTopics::DICT, $event->toArray());
    }

    /**
     * 发送在线用户数
     */
    public function sendOnlineCount(): void
    {
        $count = $this->registry->getOnlineUserCount();
        $this->broadcast(SseTopics::ONLINE_COUNT, $count);
    }

    /**
     * 向指定用户发送事件
     */
    public function sendToUser(string $username, string $eventName, mixed $data): void
    {
        $emitters = $this->registry->getUserEmitters($username);
        if ($emitters === null) {
            return;
        }
        foreach ($emitters as $emitter) {
            try {
                $emitter->sendEvent($eventName, $data);
            } catch (\Throwable $e) {
                $this->registry->removeEmitter($emitter);
            }
        }
    }

    /**
     * 获取在线用户列表
     */
    public function getOnlineUsers(): array
    {
        return $this->registry->getOnlineUsers();
    }

    /**
     * 获取在线用户数
     */
    public function getOnlineUserCount(): int
    {
        return $this->registry->getOnlineUserCount();
    }

    /**
     * 发送系统消息
     */
    public function sendSystemMessage(string $message): void
    {
        $systemMessage = [
            'sender' => '系统通知',
            'content' => $message,
            'timestamp' => (int) (microtime(true) * 1000),
        ];
        $this->broadcast(SseTopics::SYSTEM, $systemMessage);
    }

    /**
     * 移除连接
     */
    public function removeEmitter(SseEmitter $emitter): void
    {
        $this->registry->removeEmitter($emitter);
    }

    /**
     * 广播事件到所有连接
     */
    public function broadcast(string $eventName, mixed $data): void
    {
        $emitters = $this->registry->getAllEmitters();
        foreach ($emitters as $emitter) {
            try {
                $emitter->sendEvent($eventName, $data);
            } catch (\Throwable $e) {
                $this->registry->removeEmitter($emitter);
            }
        }
    }
}
