<?php declare(strict_types=1);

namespace app\support\security;

final class JwtClaimConstants
{
    // JWT 载荷字段名约�?    public const TOKEN_TYPE = 'tokenType';
    public const USER_ID = 'userId';
    public const DEPT_ID = 'deptId';
    public const DATA_SCOPE = 'dataScope';
    public const DATA_SCOPES = 'dataScopes';
    public const AUTHORITIES = 'authorities';
    public const TOKEN_VERSION = 'tokenVersion';
}
