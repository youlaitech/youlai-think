<?php declare(strict_types=1);

namespace app\common\util;

/**
 * 分页参数解析工具
 */
final class PageUtil
{
    /**
     * 解析分页参数
     *
     * @param array $params 查询参数
     * @return array{0: int, 1: int} [pageNum, pageSize]
     */
    public static function resolve(array $params): array
    {
        return [
            max(1, (int) ($params['pageNum'] ?? 1)),
            min(100, max(1, (int) ($params['pageSize'] ?? 10))),
        ];
    }
}
