<?php declare(strict_types=1);

namespace app\common\sse;

/**
 * SSE 发送器封装
 */
class SseEmitter
{
    private bool $closed = false;
    private $lastEvent = null;

    /**
     * 发送SSE事件
     */
    public function sendEvent(string $eventName, mixed $data): void
    {
        if ($this->closed) {
            return;
        }
        $this->lastEvent = ['event' => $eventName, 'data' => $data];
    }

    /**
     * 获取最后的事件
     */
    public function getLastEvent(): ?array
    {
        return $this->lastEvent;
    }

    /**
     * 清除最后的事件
     */
    public function clearLastEvent(): void
    {
        $this->lastEvent = null;
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * 检查是否已关闭
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
}
