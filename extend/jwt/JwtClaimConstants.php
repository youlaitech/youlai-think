<?php declare(strict_types=1);

namespace extend\jwt;

/**
 * JWT 载荷（payload）字段名常量
 */
class JwtClaimConstants
{
    public const USER_ID = 'user_id';
    public const USERNAME = 'username';
    public const NICKNAME = 'nickname';
    public const TOKEN_VERSION = 'token_version';
}
