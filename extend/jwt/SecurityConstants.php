<?php declare(strict_types=1);

namespace extend\jwt;

/**
 * JWT/Redis 安全相关常量
 */
class SecurityConstants
{
    public const REDIS_USER_ACCESS_TOKEN = 'auth:user:access:%d';
    public const REDIS_USER_REFRESH_TOKEN = 'auth:user:refresh:%d';
    public const REDIS_USER_TOKEN_VERSION = 'auth:user:token_version:%d';
    public const REDIS_BLACKLIST_TOKEN = 'auth:token:blacklist:%s';
}
