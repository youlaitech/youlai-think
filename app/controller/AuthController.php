<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\AuthService;

/**
 * 认证接口 /api/v1/auth
 *
 * 登录 刷新令牌 退出登录
 */
final class AuthController extends ApiController
{
    /**
     * 登录
     *
     * @return \think\Response
     * @throws BusinessException 参数缺失或认证失败时抛出
     */
    public function login(): \think\Response
    {
        $params = $this->mergeJsonParams();

        $username = trim((string) ($params['username'] ?? ''));
        $password = (string) ($params['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $token = (new AuthService())->login($username, $password);
        return $this->ok($token->toArray());
    }

    /**
     * 刷新访问令牌
     *
     * @return \think\Response
     * @throws BusinessException 刷新令牌无效或已过期时抛出
     */
    public function refreshToken(): \think\Response
    {
        $refreshToken = (string) $this->request->param('refreshToken', '');
        if ($refreshToken === '') {
            $json = $this->getJsonBody();
            $refreshToken = (string) ($json['refreshToken'] ?? '');
        }
        $token = (new AuthService())->refreshToken($refreshToken);
        return $this->ok($token->toArray());
    }

    /**
     * 退出登录
     *
     * @return \think\Response
     */
    public function logout(): \think\Response
    {
        $headerName = (string) config('security.token_header');
        $tokenPrefix = (string) config('security.token_prefix');

        $raw = (string) $this->request->header($headerName);
        if ($raw === '') {
            $raw = (string) $this->request->header(strtolower($headerName));
        }

        $accessToken = null;
        if ($raw !== '') {
            $accessToken = str_starts_with($raw, $tokenPrefix) ? substr($raw, strlen($tokenPrefix)) : $raw;
            $accessToken = trim((string) $accessToken);
        }

        (new AuthService())->logout($accessToken, null);
        return $this->ok();
    }
}
