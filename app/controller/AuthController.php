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
    public function captcha(): \think\Response
    {
        $data = (new AuthService())->getCaptcha();
        return $this->ok($data);
    }

    public function sendLoginSmsCode(): \think\Response
    {
        $mobile = trim((string) $this->request->param('mobile', ''));
        if ($mobile === '') {
            $json = $this->getJsonBody();
            $mobile = trim((string) ($json['mobile'] ?? ''));
        }

        if ($mobile === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        (new AuthService())->sendSmsLoginCode($mobile);
        return $this->ok();
    }

    public function loginBySms(): \think\Response
    {
        $mobile = trim((string) $this->request->param('mobile', ''));
        $code = trim((string) $this->request->param('code', ''));

        if ($mobile === '' || $code === '') {
            $json = $this->getJsonBody();
            $mobile = $mobile !== '' ? $mobile : trim((string) ($json['mobile'] ?? ''));
            $code = $code !== '' ? $code : trim((string) ($json['code'] ?? ''));
        }

        if ($mobile === '' || $code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $token = (new AuthService())->loginBySms($mobile, $code);
        return $this->ok($token->toArray());
    }

    public function loginByWechat(): \think\Response
    {
        $code = trim((string) $this->request->param('code', ''));
        if ($code === '') {
            $json = $this->getJsonBody();
            $code = trim((string) ($json['code'] ?? ''));
        }

        if ($code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $token = (new AuthService())->loginByWechat($code);
        return $this->ok($token->toArray());
    }

    public function loginByWxMiniAppCode(): \think\Response
    {
        $data = $this->mergeJsonParams();
        $token = (new AuthService())->loginByWxMiniAppCode($data);
        return $this->ok($token->toArray());
    }

    public function loginByWxMiniAppPhone(): \think\Response
    {
        $data = $this->mergeJsonParams();
        $token = (new AuthService())->loginByWxMiniAppPhone($data);
        return $this->ok($token->toArray());
    }

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
        $captchaId = trim((string) ($params['captchaId'] ?? ''));
        $captchaCode = trim((string) ($params['captchaCode'] ?? ''));

        if ($username === '' || $password === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        if ($captchaId === '' || $captchaCode === '') {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        $token = (new AuthService())->login($username, $password, $captchaId, $captchaCode);
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
