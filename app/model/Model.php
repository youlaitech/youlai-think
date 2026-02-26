<?php declare(strict_types=1);

namespace app\model;

use think\model\concern\SoftDelete;

/**
 * 模型基类
 *
 * 提供通用功能：
 * - 自动时间戳
 * - 软删除支持
 * - ID 访问器（解决 JS 精度问题）
 */
abstract class Model extends \think\Model
{
    use SoftDelete;

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 软删除字段
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    // 隐藏的字段
    protected $hidden = ['delete_time'];

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
     * 批量设置 ID 字段访问器
     * 子类可调用此方法为 xxxId 字段添加访问器
     */
    protected function stringifyIdFields(): void
    {
        // 由子类实现具体逻辑
    }
}
