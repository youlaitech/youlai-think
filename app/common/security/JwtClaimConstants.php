<?php

declare(strict_types=1);

namespace app\common\security;

final class JwtClaimConstants
{
    // JWT 载荷字段名约定
    public const TOKEN_TYPE = 'tokenType';
    public const USER_ID = 'userId';
    public const DEPT_ID = 'deptId';
    public const DATA_SCOPE = 'dataScope';
    public const AUTHORITIES = 'authorities';
    public const SECURITY_VERSION = 'securityVersion';
}
