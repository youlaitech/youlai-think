<?php declare(strict_types=1);

namespace app\common\traits;

/**
 * 分页参数Trait。
 * 统一处理分页参数获取，避免重复代码。
 */
trait PaginationTrait
{
    /**
     * 获取分页参数
     */
    protected function getPaginationParams(): array
    {
        $pageNum = (int) $this->request->param('pageNum', 1);
        $pageSize = (int) $this->request->param('pageSize', 10);

        // 限制每页最大条数
        $pageSize = min($pageSize, 200);
        $pageSize = max($pageSize, 1);

        return [
            'page' => max($pageNum, 1),
            'pageSize' => $pageSize,
        ];
    }

    /**
     * 获取排序参数
     */
    protected function getOrderParams(string $defaultField = 'id', string $defaultOrder = 'desc'): array
    {
        $field = $this->request->param('sortField', $defaultField);
        $order = $this->request->param('sortOrder', $defaultOrder);

        // 防止 SQL 注入
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        return [$field => $order];
    }
}
