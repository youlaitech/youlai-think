<?php declare(strict_types=1);

namespace app\common\model;

/**
 * 数据库基础模型
 *
 * 提供通用功能：
 * - 自动时间戳
 * - ID 字段自动转字符串（解决 JS 精度问题）
 * - 全局软删除过滤（is_deleted = 0）
 */
abstract class BaseModel extends \think\Model
{
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 隐藏的字段
    protected $hidden = ['is_deleted'];

    // 全局查询作用域
    protected $globalScope = ['soft_delete'];

    /**
     * 软删除全局作用域
     */
    public function scopeSoftDelete($query)
    {
        $query->where(function ($q) {
            $q->where('is_deleted', 0)->whereOr('is_deleted', null);
        });
    }

    /**
     * 获取表名（不含前缀）
     */
    public static function getTableName(): string
    {
        return (new static())->getName();
    }

    /**
     * 序列化时将 ID 字段转为字符串，避免前端 JS 精度丢失
     * 匹配规则：字段名为 id、*_id、*_by 且值为整数
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        array_walk_recursive($array, static function (mixed &$value, mixed $key): void {
            if (is_int($value) && (
                $key === 'id'
                || str_ends_with((string) $key, '_id')
                || str_ends_with((string) $key, '_by')
            )) {
                $value = (string) $value;
            }
        });
        return $array;
    }

    /**
     * 包含已删除记录的查询（绕过全局作用域）
     */
    public static function withTrashed(): static
    {
        return (new static())->removeGlobalScope('soft_delete');
    }

    /**
     * 仅查询已删除记录
     */
    public static function onlyTrashed(): static
    {
        return (new static())
            ->removeGlobalScope('soft_delete')
            ->where('is_deleted', 1);
    }

    /**
     * 软删除
     */
    public function softDelete(): bool
    {
        $this->is_deleted = 1;
        return $this->save();
    }
}

