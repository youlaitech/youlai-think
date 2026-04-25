<?php declare(strict_types=1);

namespace extend\sse;

/**
 * SSE 事件发射器
 * 直接向底层连接推送 text/event-stream 数据
 */
class SseEmitter
{
    private $connection;
    private bool $closed = false;

    public function __construct($connection) { $this->connection = $connection; }

    public function sendEvent(string $eventName, mixed $data): bool
    {
        if ($this->closed) return false;
        try {
            if ($eventName === "" && $data === "") {
                // 心跳：SSE 规范的注释行
                return $this->rawSend(": heartbeat\n\n");
            }
            // 构造标准 SSE 格式，使用 rawSend 绕过 Workerman HTTP 协议编码器
            $sse = "event: {$eventName}\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            return $this->rawSend($sse);
        } catch (\Throwable) { $this->close(); return false; }
    }

    /**
     * 发送 SSE HTTP 响应头（raw 模式，绕过 Workerman 协议编码器）
     */
    public function sendHeaders(): bool
    {
        return $this->rawSend(
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/event-stream\r\n" .
            "Cache-Control: no-cache\r\n" .
            "Connection: keep-alive\r\n" .
            "X-Accel-Buffering: no\r\n" .
            "Access-Control-Allow-Origin: *\r\n" .
            "\r\n"
        );
    }

    /**
     * 将 HTTP 响应头和初始 SSE 事件合并为一次 raw send
     * 确保代理收到的响应体不为空，防止代理在空响应期间关闭连接
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
     * 使用 raw 模式发送数据，绕过 Workerman 的 HTTP 协议编码器
     * 避免编码器对 SSE 流式响应添加 Content-Length 或创建新 HTTP 响应
     */
    private function rawSend(string $data): bool
    {
        if ($this->connection === null) return false;
        try {
            if ($this->connection instanceof \Workerman\Connection\TcpConnection) {
                // 第二个参数 true 表示 raw 模式，跳过 protocol::encode()
                $this->connection->send($data, true);
                return true;
            }
            if (is_callable([$this->connection, 'write'])) { $this->connection->write($data); return true; }
            if (is_resource($this->connection)) { fwrite($this->connection, $data); return true; }
            return false;
        } catch (\Throwable) { $this->close(); return false; }
    }

    public function close(): void
    {
        $this->closed = true;
        if ($this->connection instanceof \Workerman\Connection\TcpConnection) { try { $this->connection->close(); } catch (\Throwable) {} }
        $this->connection = null;
    }

    public function isClosed(): bool { return $this->closed; }
    public function getConnection(): mixed { return $this->connection; }
}
