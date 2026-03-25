<?php declare(strict_types=1);

namespace app\common\model;

/**
 * 数据库基础模型
 *
 * 提供通用功能：
 * - 自动时间戳
 * - ID 访问器（解决 JS 精度问题）
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
        $query->where('is_deleted', 0);
    }

    /**
     * 获取表名（不含前缀）
     */
    public static function getTableName(): string
    {
        return (new static())->getName();
    }

    /**
     * ID 字段访问器 - 转为字符串避免 JS 精度丢失
     */
    public function getIdAttr(mixed $value): string
    {
        return (string) $value;
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

