<?php declare(strict_types=1);

namespace app\common\constants;

/**
 * Redis 键常量
 */
final class RedisKey
{
    /** 图片验证码键前缀 */
    public const string CAPTCHA = 'captcha:';

    /** 短信登录验证码键前缀 */
    public const string SMS_LOGIN = 'sms:login:';

    /** 短信绑定手机验证码键前缀 */
    public const string SMS_BIND_MOBILE = 'sms:bind:mobile:';

    /** 短信绑定邮箱验证码键前缀 */
    public const string SMS_BIND_EMAIL = 'sms:bind:email:';

    /** 用户 Token 键前缀 */
    public const string USER_TOKEN = 'user:token:';

    /** 用户刷新 Token 键前缀 */
    public const string USER_REFRESH_TOKEN = 'user:refresh:token:';

    /** 用户会话键前缀 */
    public const string USER_SESSION = 'user:session:';

    /** 角色权限缓存键前缀 */
    public const string ROLE_PERMS = 'role:perms:';

    /** 角色菜单缓存键前缀 */
    public const string ROLE_MENUS = 'role:menus:';

    /** 数据权限缓存键前缀 */
    public const string DATA_SCOPE = 'data:scope:';

    /** IP 限流键前缀 */
    public const string RATE_LIMIT_IP = 'rate_limiter:ip:';

    /**
     * 构建验证码键
     */
    public static function captcha(string $id): string
    {
        return self::CAPTCHA . $id;
    }

    /**
     * 构建短信登录验证码键
     */
    public static function smsLogin(string $mobile): string
    {
        return self::SMS_LOGIN . $mobile;
    }

    /**
     * 构建短信绑定手机验证码键
     */
    public static function smsBindMobile(string $mobile): string
    {
        return self::SMS_BIND_MOBILE . $mobile;
    }

    /**
     * 构建短信绑定邮箱验证码键
     */
    public static function smsBindEmail(string $email): string
    {
        return self::SMS_BIND_EMAIL . $email;
    }

    /**
     * 构建用户 Token 键
     */
    public static function userToken(int $userId): string
    {
        return self::USER_TOKEN . $userId;
    }

    /**
     * 构建用户会话键
     */
    public static function userSession(int $userId): string
    {
        return self::USER_SESSION . $userId;
    }

    /**
     * 构建角色权限缓存键
     */
    public static function rolePerms(string $roleCode): string
    {
        return self::ROLE_PERMS . $roleCode;
    }

    /**
     * 构建角色菜单缓存键
     */
    public static function roleMenus(string $roleCode): string
    {
        return self::ROLE_MENUS . $roleCode;
    }

    /**
     * 构建 IP 限流键
     */
    public static function rateLimitIp(string $ip): string
    {
        return self::RATE_LIMIT_IP . $ip;
    }

    private function __construct()
    {
        // 禁止实例化
    }
}
