<?php declare(strict_types=1);

namespace app\common\traits;

/**
 * 分页查询 Trait
 */
trait PaginationTrait
{
    /**
     * 获取分页参数
     */
    protected function getPageParams(): array
    {
        $page = (int) request()->param('page', 1);
        $pageSize = (int) request()->param('pageSize', 10);

        // 限制每页最大条数
        $pageSize = min($pageSize, 100);

        return [$page, $pageSize];
    }

    /**
     * 构建分页查询
     */
    protected function buildPaginatedQuery($query, int $page, int $pageSize)
    {
        $total = $query->count();
        $list = $query->page($page, $pageSize)->select();

        return [
            'list' => $list,
            'total' => $total,
        ];
    }
}