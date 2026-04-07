<?php declare(strict_types=1);

namespace app\common\enums;

/**
 * 数据范围枚举
 */
enum DataScopeEnum: int
{
    case ALL = 1;           // 全部数据权限
    case DEPT_AND_SUB = 2;  // 本部门及以下数据权限
    case DEPT = 3;          // 本部门数据权限
    case SELF = 4;          // 仅本人数据权限
    case CUSTOM = 5;        // 自定义数据权限

    public function label(): string
    {
        return match ($this) {
            self::ALL => '全部数据权限',
            self::DEPT => '本部门数据权限',
            self::DEPT_AND_SUB => '本部门及以下数据权限',
            self::SELF => '仅本人数据权限',
            self::CUSTOM => '自定义数据权限',
        };
    }
}
