<?php declare(strict_types=1);

namespace app\system\enums;

use JsonSerializable;

/**
 * 操作类型枚举
 */
enum ActionType: int implements JsonSerializable
{
    case LOGIN = 1;
    case LOGOUT = 2;
    case USER_LIST = 3;
    case USER_CREATE = 4;
    case USER_UPDATE = 5;
    case USER_DELETE = 6;
    case ROLE_LIST = 7;
    case ROLE_CREATE = 8;
    case ROLE_UPDATE = 9;
    case ROLE_DELETE = 10;
    case MENU_CREATE = 11;
    case MENU_UPDATE = 12;
    case MENU_DELETE = 13;
    case DEPT_CREATE = 14;
    case DEPT_UPDATE = 15;
    case DEPT_DELETE = 16;
    case DICT_CREATE = 17;
    case DICT_UPDATE = 18;
    case DICT_DELETE = 19;
    case CONFIG_CREATE = 20;
    case CONFIG_UPDATE = 21;
    case CONFIG_DELETE = 22;
    case CHANGE_PWD = 23;
    case UPDATE_PROFILE = 24;
    case EXPORT = 25;
    case IMPORT = 26;
    case OTHER = 99;

    public function description(): string
    {
        return match($this) {
            self::LOGIN => '登录',
            self::LOGOUT => '登出',
            self::USER_LIST => '用户列表',
            self::USER_CREATE => '新增用户',
            self::USER_UPDATE => '修改用户',
            self::USER_DELETE => '删除用户',
            self::ROLE_LIST => '角色列表',
            self::ROLE_CREATE => '新增角色',
            self::ROLE_UPDATE => '修改角色',
            self::ROLE_DELETE => '删除角色',
            self::MENU_CREATE => '新增菜单',
            self::MENU_UPDATE => '修改菜单',
            self::MENU_DELETE => '删除菜单',
            self::DEPT_CREATE => '新增部门',
            self::DEPT_UPDATE => '修改部门',
            self::DEPT_DELETE => '删除部门',
            self::DICT_CREATE => '新增字典',
            self::DICT_UPDATE => '修改字典',
            self::DICT_DELETE => '删除字典',
            self::CONFIG_CREATE => '新增配置',
            self::CONFIG_UPDATE => '修改配置',
            self::CONFIG_DELETE => '删除配置',
            self::CHANGE_PWD => '修改密码',
            self::UPDATE_PROFILE => '修改个人信息',
            self::EXPORT => '导出',
            self::IMPORT => '导入',
            self::OTHER => '其他',
        };
    }

    public function jsonSerialize(): string
    {
        return $this->description();
    }

    public static function getLabel(int $value): string
    {
        return self::tryFrom($value)?->description() ?? '其他';
    }
}
