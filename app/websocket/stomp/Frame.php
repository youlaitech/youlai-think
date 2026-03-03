<?php declare(strict_types=1);

namespace app\websocket\stomp;

/**
 * STOMP 帧解析器
 */
class Frame
{
    public string $command;
    public array $headers;
    public ?string $body;

    public function __construct(string $command, array $headers = [], ?string $body = null)
    {
        $this->command = $command;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * 从字符串解析 STOMP 帧
     */
    public static function parse(string $data): ?self
    {
        if (empty($data)) {
            return null;
        }

        // STOMP 帧格式: COMMAND\nheader1:value1\nheader2:value2\n\nbody^@
        $parts = explode("\n\n", $data, 2);
        if (count($parts) < 1) {
            return null;
        }

        $lines = explode("\n", $parts[0]);
        $command = trim(array_shift($lines));
        $headers = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $key = substr($line, 0, $colonPos);
                $value = substr($line, $colonPos + 1);
                $headers[$key] = $value;
            }
        }

        $body = $parts[1] ?? null;
        // 移除终止符 (null byte)
        if ($body !== null) {
            $body = str_replace("\x00", '', $body);
        }

        return new self($command, $headers, $body);
    }

    /**
     * 编码为 STOMP 帧字符串
     */
    public function encode(): string
    {
        $frame = $this->command . "\n";

        foreach ($this->headers as $key => $value) {
            // 转义特殊字符
            $value = str_replace(['\\', "\n", ':'], ['\\\\', '\\n', '\\c'], (string)$value);
            $frame .= $key . ':' . $value . "\n";
        }

        $frame .= "\n";

        if ($this->body !== null) {
            $frame .= $this->body;
        }

        $frame .= "\x00";

        return $frame;
    }

    /**
     * 获取指定 header 值
     */
    public function getHeader(string $name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }
}
