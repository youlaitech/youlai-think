<?php declare(strict_types=1);

namespace extend\sse;

use Workerman\Connection\TcpConnection;

/**
 * SSE 事件发射器，直接向底层连接写入 text/event-stream 数据
 */
class SseEmitter
{
    private $connection;
    private bool $closed = false;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * 发送 SSE 事件（eventName 和 data 均为空时发心跳注释行）
     */
    public function sendEvent(string $eventName, mixed $data): bool
    {
        if ($this->closed) return false;

        $sse = $eventName === '' && $data === ''
            ? ": heartbeat\n\n"
            : "event: {$eventName}\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

        return $this->rawSend($sse);
    }

    /**
     * 合并 HTTP 响应头和初始事件为一次写入，防止代理在空响应期间关闭连接
     */
    public function sendHeadersWithEvent(string $eventName, mixed $data): bool
    {
        $sseEvent = "event: {$eventName}\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        return $this->rawSend(
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/event-stream\r\n" .
            "Cache-Control: no-cache\r\n" .
            "Connection: keep-alive\r\n" .
            "X-Accel-Buffering: no\r\n" .
            "Access-Control-Allow-Origin: *\r\n" .
            "\r\n" .
            $sseEvent
        );
    }

    /**
     * raw 模式发送，绕过 Workerman HTTP 协议编码器
     */
    private function rawSend(string $data): bool
    {
        if ($this->connection === null) return false;

        try {
            if ($this->connection instanceof TcpConnection) {
                $this->connection->send($data, true); // 第二参数 true = raw 模式
                return true;
            }
            if (is_callable([$this->connection, 'write'])) {
                $this->connection->write($data);
                return true;
            }
            if (is_resource($this->connection)) {
                fwrite($this->connection, $data);
                return true;
            }
            return false;
        } catch (\Throwable) {
            $this->close();
            return false;
        }
    }

    public function close(): void
    {
        if ($this->closed) return;
        $this->closed = true;

        if ($this->connection instanceof TcpConnection) {
            try { $this->connection->close(); } catch (\Throwable) {}
        }
        $this->connection = null;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }
}
