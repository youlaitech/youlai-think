<?php declare(strict_types=1);

namespace app\common\model;

/**
 * 模型基类
 *
 * 提供通用功能：
 * - 自动时间戳
 * - ID 访问器（解决 JS 精度问题）
 */
abstract class Model extends \think\Model
{
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 隐藏的字段
    protected $hidden = ['is_deleted'];

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
}
