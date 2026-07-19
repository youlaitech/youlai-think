<?php declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

abstract class BaseValidate extends Validate
{
    public function checkOrFail(array $data): array
    {
        if (!$this->check($data)) {
            throw new \think\exception\ValidateException($this->getError());
        }
        return $data;
    }

    /**
     * 数据库存在性校验，用法：exist:table,column
     */
    public function exist(mixed $value, string $rule): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        [$table, $column] = explode(',', $rule);
        return app()->db->name($table)->where($column, $value)->where('is_deleted', 0)->find() !== null;
    }

    /**
     * 字段唯一性校验，用法：unique:table,column,exceptId
     */
    public function unique($value, $rule, array $data = [], string $field = ''): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $parts = explode(',', $rule);
        $table = $parts[0];
        $column = $parts[1] ?? $field;
        $exceptId = $parts[2] ?? null;

        $query = app()->db->name($table)->where($column, $value);
        if ($exceptId !== null && isset($data[$exceptId])) {
            $query->where('id', '<>', $data[$exceptId]);
        }
        $query->where('is_deleted', 0);
        return $query->count() === 0;
    }

    public function isMobile(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return (bool) preg_match('/^1[3-9]\d{9}$/', (string) $value);
    }
}
