<?php declare(strict_types=1);

namespace app\common\web;

/**
 * 分页查询的 list + total 包装
 */
final class PageResult
{
    public function __construct(
        public readonly array $list,
        public readonly int $total,
    ) {}

    public function toArray(): array
    {
        return [
            'list' => $this->list,
            'total' => $this->total,
        ];
    }
}
