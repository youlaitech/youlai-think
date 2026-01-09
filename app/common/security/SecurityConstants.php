<?php

declare(strict_types=1);

namespace app\common\security;

final class SecurityConstants
{
    public const LOGIN_PATH = '/api/v1/auth/login';
    public const BEARER_TOKEN_PREFIX = 'Bearer ';
    public const ROLE_PREFIX = 'ROLE_';
}
