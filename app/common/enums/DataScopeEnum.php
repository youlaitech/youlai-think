<?php

declare(strict_types=1);

namespace app\common\enums;

/**
 * 数据权限枚举
 * value 越小，数据权限范围越大。
 * 多角色数据权限合并策略：取并集（OR），即用户能看到所有角色权限范围内的数据。
 * 如果任一角色是 ALL，则直接跳过数据权限过滤。
 */
class DataScopeEnum
{
    /** 所有数据权限 - 最高权限，可查看所有数据 */
    public const ALL = 1;

    /** 部门及子部门数据 - 可查看本部门及其下属所有部门的数据 */
    public const DEPT_AND_SUB = 2;

    /** 本部门数据 - 仅可查看本部门的数据 */
    public const DEPT = 3;

    /** 本人数据 - 仅可查看自己的数据 */
    public const SELF = 4;

    /** 自定义部门数据 - 可查看指定部门的数据 */
    public const CUSTOM = 5;

    /**
     * 所有枚举值
     */
    public const ALL_VALUES = [
        self::ALL,
        self::DEPT_AND_SUB,
        self::DEPT,
        self::SELF,
        self::CUSTOM,
    ];

    /**
     * 枚举标签映射
     */
    public const LABELS = [
        self::ALL => '所有数据',
        self::DEPT_AND_SUB => '部门及子部门数据',
        self::DEPT => '本部门数据',
        self::SELF => '本人数据',
        self::CUSTOM => '自定义部门数据',
    ];

    /**
     * 根据值获取标签
     */
    public static function getLabel(int $value): string
    {
        return self::LABELS[$value] ?? '未知';
    }

    /**
     * 判断是否为全部数据权限
     */
    public static function isAll(?int $value): bool
    {
        return $value === self::ALL;
    }

    /**
     * 根据值获取枚举
     */
    public static function getByValue(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }
        return in_array($value, self::ALL_VALUES) ? $value : null;
    }
}
