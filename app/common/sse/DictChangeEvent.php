<?php declare(strict_types=1);

namespace app\common\sse;

/**
 * 字典变更事件DTO
 */
class DictChangeEvent
{
    public string $dictCode;
    public int $timestamp;

    public function __construct(string $dictCode)
    {
        $this->dictCode = $dictCode;
        $this->timestamp = (int) (microtime(true) * 1000);
    }

    public function toArray(): array
    {
        return [
            'dictCode' => $this->dictCode,
            'timestamp' => $this->timestamp,
        ];
    }
}
