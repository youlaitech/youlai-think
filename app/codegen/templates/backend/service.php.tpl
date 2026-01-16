<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * {{entityName}} 服务
 */
final class {{entityName}}Service
{
    /**
     * 分页查询
     */
    public function page(array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $q = Db::name('{{tableName}}'){{softDeleteWhere}};
        $total = (int) (clone $q)->count('id');
        $rows = $q->field('{{fieldSql}}')->order('id', 'desc')->page($pageNum, $pageSize)->select()->toArray();

        return [$rows, $total];
    }

    /**
     * 表单数据
     */
    public function getFormData(int $id): array
    {
        $row = Db::name('{{tableName}}')->where('id', $id){{softDeleteWhere}}->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '{{entityName}} 不存在');
        }

        return $row;
    }

    /**
     * 新增
     */
    public function create(array $data): bool
    {
{{createDataMerge}}        Db::name('{{tableName}}')->insert($data);
        return true;
    }

    /**
     * 修改
     */
    public function update(int $id, array $data): bool
    {
{{updateDataMerge}}        Db::name('{{tableName}}')->where('id', $id)->update($data);
        return true;
    }

    /**
     * 删除
     */
    public function delete(string $ids): bool
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $ids)), fn($v) => $v !== ''));
        $idList = array_values(array_filter(array_map('intval', $parts), fn($v) => $v > 0));
        if (empty($idList)) {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

{{deleteBody}}
        return true;
    }
}
