<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\security\AuthenticationToken;
use app\common\security\TokenManagerResolver;
use app\common\web\ResultCode;

final class AuthService
{
    public function __construct(
        private readonly UserService $userService = new UserService(),
    ) {
    }

    public function login(string $username, string $password): AuthenticationToken
    {
        $user = $this->userService->getUserByUsername($username);

        if ($user === null) {
            throw new BusinessException(ResultCode::ACCOUNT_NOT_FOUND);
        }

        if ((int) ($user['status'] ?? 1) !== 1) {
            throw new BusinessException(ResultCode::ACCOUNT_FROZEN);
        }

        $hash = (string) ($user['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            throw new BusinessException(ResultCode::USER_PASSWORD_ERROR);
        }

        $authInfo = [
            'userId' => (int) $user['id'],
            'deptId' => $user['dept_id'] ?? null,
            'dataScope' => null,
            'authorities' => [],
        ];

        return (new TokenManagerResolver())->get()->generateToken($authInfo);
    }

    public function refreshToken(string $refreshToken): AuthenticationToken
    {
        if ($refreshToken === '') {
            throw new BusinessException(ResultCode::REFRESH_TOKEN_INVALID);
        }

        return (new TokenManagerResolver())->get()->refreshToken($refreshToken);
    }

    public function logout(?string $accessToken, ?string $refreshToken): void
    {
        (new TokenManagerResolver())->get()->invalidate($accessToken, $refreshToken);
    }
}
