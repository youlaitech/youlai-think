<?php declare(strict_types=1);

namespace app\common\constants;

/**
 * Redis Key 常量
 */
class RedisConstants
{
    // 验证码相关
    public const CAPTCHA_CODE = 'captcha:code:{}';
    public const SMS_CODE = 'sms:code:{}';
    public const EMAIL_CODE = 'email:code:{}';

    // 用户认证相关
    public const USER_TOKEN_VERSION = 'auth:user:token_version:{}';
    public const USER_ACCESS_TOKEN = 'auth:user:access:{}';
    public const USER_REFRESH_TOKEN = 'auth:user:refresh:{}';
    public const TOKEN_BLACKLIST = 'auth:token:blacklist:{}';
}
