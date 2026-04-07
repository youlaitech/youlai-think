<?php declare(strict_types=1);

namespace app\system\enums;

use JsonSerializable;

/**
 * 日志模块枚举
 */
enum LogModule: int implements JsonSerializable
{
    /** 登录 */
    case LOGIN = 1;
    /** 用户管理 */
    case USER = 2;
    /** 角色管理 */
    case ROLE = 3;
    /** 部门管理 */
    case DEPT = 4;
    /** 菜单管理 */
    case MENU = 5;
    /** 字典管理 */
    case DICT = 6;
    /** 系统配置 */
    case CONFIG = 7;
    /** 文件管理 */
    case FILE = 8;
    /** 通知公告 */
    case NOTICE = 9;
    /** 日志管理 */
    case LOG = 10;
    /** 代码生成 */
    case CODEGEN = 11;
    /** 其他 */
    case OTHER = 99;

    public function description(): string
    {
        return match($this) {
            self::LOGIN => '登录',
            self::USER => '用户管理',
            self::ROLE => '角色管理',
            self::DEPT => '部门管理',
            self::MENU => '菜单管理',
            self::DICT => '字典管理',
            self::CONFIG => '系统配置',
            self::FILE => '文件管理',
            self::NOTICE => '通知公告',
            self::LOG => '日志管理',
            self::CODEGEN => '代码生成',
            self::OTHER => '其他',
        };
    }

    /**
     * JSON 序列化返回描述文本
     */
    public function jsonSerialize(): string
    {
        return $this->description();
    }

    /**
     * 从数值获取描述文本
     */
    public static function getLabel(int $value): string
    {
        return self::tryFrom($value)?->description() ?? '其他';
    }
}
