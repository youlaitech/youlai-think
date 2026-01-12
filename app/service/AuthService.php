<?php

declare(strict_types=1);

namespace app\service;

use app\common\redis\RedisClient;
use app\common\redis\RedisKey;
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

    public function sendSmsLoginCode(string $mobile): void
    {
        $mobile = trim($mobile);
        if ($mobile === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $code = '1234';
        $key = RedisKey::format('captcha:sms_login:{}', $mobile);
        RedisClient::get()->setex($key, 300, $code);
    }

    public function loginBySms(string $mobile, string $code): AuthenticationToken
    {
        $mobile = trim($mobile);
        $code = trim($code);

        if ($mobile === '' || $code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $key = RedisKey::format('captcha:sms_login:{}', $mobile);
        $cachedCode = (string) (RedisClient::get()->get($key) ?? '');
        if ($cachedCode === '') {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_EXPIRED);
        }

        if (strcasecmp($cachedCode, $code) !== 0) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        RedisClient::get()->del([$key]);

        $user = $this->userService->getUserByMobile($mobile);
        if ($user === null) {
            throw new BusinessException(ResultCode::ACCOUNT_NOT_FOUND);
        }

        if ((int) ($user['status'] ?? 1) !== 1) {
            throw new BusinessException(ResultCode::ACCOUNT_FROZEN);
        }

        $authInfo = [
            'userId' => (int) $user['id'],
            'deptId' => $user['dept_id'] ?? null,
            'dataScope' => null,
            'authorities' => [],
        ];

        return (new TokenManagerResolver())->get()->generateToken($authInfo);
    }

    public function loginByWechat(string $code): AuthenticationToken
    {
        $code = trim($code);
        if ($code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $user = $this->userService->getUserByOpenid($code);
        if ($user === null) {
            throw new BusinessException(ResultCode::ACCOUNT_NOT_FOUND);
        }

        if ((int) ($user['status'] ?? 1) !== 1) {
            throw new BusinessException(ResultCode::ACCOUNT_FROZEN);
        }

        $authInfo = [
            'userId' => (int) $user['id'],
            'deptId' => $user['dept_id'] ?? null,
            'dataScope' => null,
            'authorities' => [],
        ];

        return (new TokenManagerResolver())->get()->generateToken($authInfo);
    }

    public function loginByWxMiniAppCode(array $data): AuthenticationToken
    {
        $code = trim((string) ($data['code'] ?? ''));
        return $this->loginByWechat($code);
    }

    public function loginByWxMiniAppPhone(array $data): AuthenticationToken
    {
        $code = trim((string) ($data['code'] ?? ''));
        return $this->loginByWechat($code);
    }

    public function getCaptcha(): array
    {
        $code = (string) random_int(1000, 9999);
        $captchaId = bin2hex(random_bytes(16));

        $key = RedisKey::format('captcha:image:{}', $captchaId);
        RedisClient::get()->setex($key, 300, $code);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="50" viewBox="0 0 160 50">'
            . '<rect width="160" height="50" fill="#f5f5f5"/>'
            . '<text x="80" y="33" text-anchor="middle" font-family="Arial, sans-serif" font-size="28" font-weight="700" fill="#111">'
            . htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</text>'
            . '</svg>';
        $captchaBase64 = 'data:image/svg+xml;base64,' . base64_encode($svg);

        return [
            'captchaId' => $captchaId,
            'captchaBase64' => $captchaBase64,
        ];
    }

    public function login(string $username, string $password, string $captchaId, string $captchaCode): AuthenticationToken
    {
        if ($captchaId === '' || $captchaCode === '') {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        $captchaKey = RedisKey::format('captcha:image:{}', $captchaId);
        $cachedCode = (string) (RedisClient::get()->get($captchaKey) ?? '');
        if ($cachedCode === '') {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_EXPIRED);
        }

        if (strcasecmp(trim($cachedCode), trim($captchaCode)) !== 0) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

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
