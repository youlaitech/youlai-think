<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\AuthService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="01.认证接口")
 */
final class AuthController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/auth/captcha",
     *     summary="获取验证码",
     *     tags={"01.认证接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function captcha(): \think\Response
    {
        $data = (new AuthService())->getCaptcha();
        return $this->ok($data);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/sms/code",
     *     summary="发送登录短信验证码",
     *     tags={"01.认证接口"},
     *     @OA\Parameter(name="mobile", in="query", description="手机号", required=true, example="18812345678"),
     *     @OA\Response(response=200, description="OK")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login/sms",
     *     summary="短信验证码登录",
     *     tags={"01.认证接口"},
     *     @OA\Parameter(name="mobile", in="query", description="手机号", required=true, example="18812345678"),
     *     @OA\Parameter(name="code", in="query", description="验证码", required=true, example="1234"),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function loginBySms(): \think\Response
    {
        $mobile = trim((string) $this->request->param('mobile', ''));
        $code = trim((string) $this->request->param('code', ''));

        if ($mobile === '' || $code === '') {
            $json = $this->getJsonBody();
            // 兼容 query/body 传参
            $mobile = $mobile !== '' ? $mobile : trim((string) ($json['mobile'] ?? ''));
            $code = $code !== '' ? $code : trim((string) ($json['code'] ?? ''));
        }

        if ($mobile === '' || $code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $token = (new AuthService())->loginBySms($mobile, $code);
        return $this->ok($token->toArray());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login/wechat",
     *     summary="微信授权登录(Web)",
     *     tags={"01.认证接口"},
     *     @OA\Parameter(name="code", in="query", description="微信授权码", required=true, example="code"),
     *     @OA\Response(response=200, description="OK")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/auth/wx/miniapp/code-login",
     *     summary="微信小程序登录(Code)",
     *     tags={"01.认证接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function loginByWxMiniAppCode(): \think\Response
    {
        $data = $this->mergeJsonParams();
        $token = (new AuthService())->loginByWxMiniAppCode($data);
        return $this->ok($token->toArray());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/wx/miniapp/phone-login",
     *     summary="微信小程序登录(手机号)",
     *     tags={"01.认证接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function loginByWxMiniAppPhone(): \think\Response
    {
        $data = $this->mergeJsonParams();
        $token = (new AuthService())->loginByWxMiniAppPhone($data);
        return $this->ok($token->toArray());
    }

    /**
     * 登录
     *
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="账号密码登录",
     *     tags={"01.认证接口"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password","captchaId","captchaCode"},
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="captchaId", type="string"),
     *             @OA\Property(property="captchaCode", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 参数缺失或认证失败时抛出
     */
    public function login(): \think\Response
    {
        // 统一合并参数，避免前端传参方式不一致
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
     * @OA\Post(
     *     path="/api/v1/auth/refresh-token",
     *     summary="刷新令牌",
     *     tags={"01.认证接口"},
     *     @OA\Parameter(name="refreshToken", in="query", description="刷新令牌", required=true, example="xxx.xxx.xxx"),
     *     @OA\Response(response=200, description="OK")
     * )
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
        // refreshToken 可走 query 或 body
        $token = (new AuthService())->refreshToken($refreshToken);
        return $this->ok($token->toArray());
    }

    /**
     * 退出登录
     *
     * @OA\Delete(
     *     path="/api/v1/auth/logout",
     *     summary="退出登录",
     *     tags={"01.认证接口"},
     *     @OA\Response(response=200, description="OK")
     * )
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
            // 兼容 Bearer 前缀
            $accessToken = str_starts_with($raw, $tokenPrefix) ? substr($raw, strlen($tokenPrefix)) : $raw;
            $accessToken = trim((string) $accessToken);
        }

        (new AuthService())->logout($accessToken, null);
        return $this->ok();
    }
}
