<?php declare(strict_types=1);

namespace app\auth\controller;

use app\common\exception\BusinessException;
use extend\redis\RedisClient;
use app\common\web\ResultCode;
use app\common\controller\BaseController;
use app\auth\service\AuthService;
use app\system\annotation\Log;
use app\system\enums\ActionType;
use app\system\model\Log as LogModel;
use Gregwar\Captcha\CaptchaBuilder;
use OpenApi\Annotations as OA;
use think\response\Json;

/**
 * @OA\Tag(name="01.认证中心")
 */
final class AuthController extends BaseController
{
    /**
     * 获取验证码
     *
     * @OA\Get(
     *     path="/api/v1/auth/captcha",
     *     summary="获取验证码",
     *     tags={"01.认证中心"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function captcha(): Json
    {
        $length = (int) config('captcha.length', 4);
        $length = $length > 0 ? $length : 4;

        $chars = (string) config('captcha.codeSet', '23456789ABCDEFGHJKLMNPQRSTUVWXYZ');
        if ($chars === '') {
            $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        }

        $imageW = (int) config('captcha.imageW', 100);
        $imageH = (int) config('captcha.imageH', 32);
        $imageW = $imageW > 0 ? $imageW : 100;
        $imageH = $imageH > 0 ? $imageH : 32;

        $bg = config('captcha.bg', [255, 255, 255]);

        $expire = (int) config('captcha.expire', 300);
        $expire = $expire > 0 ? $expire : 300;

        $phrase = '';
        $charsLen = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $phrase .= $chars[random_int(0, $charsLen - 1)];
        }

        $builder = new CaptchaBuilder($phrase);
        if (is_array($bg) && count($bg) === 3) {
            $builder->setBackgroundColor((int) $bg[0], (int) $bg[1], (int) $bg[2]);
        }
        $builder->build($imageW, $imageH);

        $captchaId = uniqid('', true);
        RedisClient::get()->setex("captcha:{$captchaId}", $expire, strtolower($phrase));
        $base64 = $builder->inline();

        return $this->success([
            'captchaId' => $captchaId,
            'captchaBase64' => $base64,
        ]);
    }

    /**
     * 登录
     *
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="账号密码登录",
     *     tags={"01.认证中心"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function login(): Json
    {
        $username = $this->getParam('username', '');
        $password = $this->getParam('password', '');
        $captchaId = $this->getParam('captchaId', '');
        $captchaCode = $this->getParam('captchaCode', '');

        // 验证码校验
        $this->validateCaptcha($captchaId, $captchaCode);

        // 执行登录
        $result = $this->service(AuthService::class)->login($username, $password);

        return $this->success($result, '登录成功');
    }

    /**
     * 登出
     *
     * @OA\Delete(
     *     path="/api/v1/auth/logout",
     *     summary="登出",
     *     tags={"01.认证中心"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::LOGOUT)]
    public function logout(): Json
    {
        $accessToken = $this->request->header('Authorization', '');

        $this->service(AuthService::class)->logout($accessToken ?: null, null);

        return $this->success(null, '登出成功');
    }

    /**
     * 刷新 Token
     *
     * @OA\Post(
     *     path="/api/v1/auth/refresh-token",
     *     summary="刷新令牌",
     *     tags={"01.认证中心"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function refresh(): Json
    {
        $refreshToken = $this->getParam('refreshToken', '');

        $result = $this->service(AuthService::class)->refresh($refreshToken);

        return $this->success($result);
    }

    /**
     * 发送登录短信验证码
     *
     * @OA\Post(
     *     path="/api/v1/auth/sms/code",
     *     summary="发送登录短信验证码",
     *     tags={"01.认证中心"},
     *     @OA\Parameter(name="mobile", in="query", description="手机号", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function sendLoginVerifyCode(): Json
    {
        $mobile = $this->getParam('mobile', '');

        if (empty($mobile)) {
            return $this->fail('A0400', '手机号不能为空');
        }

        $this->service(AuthService::class)->sendSmsLoginCode($mobile);

        return $this->success(null, '验证码发送成功');
    }

    /**
     * 短信验证码登录
     *
     * @OA\Post(
     *     path="/api/v1/auth/login/sms",
     *     summary="短信验证码登录",
     *     tags={"01.认证中心"},
     *     @OA\Parameter(name="mobile", in="query", description="手机号", required=true),
     *     @OA\Parameter(name="code", in="query", description="验证码", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::LOGIN)]
    public function loginBySms(): Json
    {
        $mobile = $this->getParam('mobile', '');
        $code = $this->getParam('code', '');

        if (empty($mobile) || empty($code)) {
            return $this->fail('A0400', '手机号和验证码不能为空');
        }

        $result = $this->service(AuthService::class)->loginBySms($mobile, $code);

        // 手动记录登录日志（登录接口是公开的，LogMiddleware 无法获取 userId）
        $this->recordLoginLogByMobile($mobile, '/api/v1/auth/login/sms');

        return $this->success($result, '登录成功');
    }

    // ==================== 私有方法 ====================

    /**
     * 验证验证码
     */
    private function validateCaptcha(string $captchaId, string $captchaCode): void
    {
        if (empty($captchaId) || empty($captchaCode)) {
            throw new BusinessException(ResultCode::USER_REQUEST_PARAMETER_ERROR, '验证码不能为空');
        }

        $redis = RedisClient::get();
        $key = "captcha:{$captchaId}";
        $storedCode = $redis->get($key);
        if (!$storedCode || strtolower((string) $storedCode) !== strtolower($captchaCode)) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        $redis->del($key);
    }

    /**
     * 手动记录用户名密码登录日志
     */
    private function recordLoginLog(string $username, string $requestUri): void
    {
        try {
            $user = \app\system\model\User::where('username', $username)->find();
            if (!$user) return;

            $this->saveLoginLog((int) $user->id, $requestUri);
        } catch (\Throwable) {
            // 日志记录失败不影响登录
        }
    }

    /**
     * 手动记录短信验证码登录日志
     */
    private function recordLoginLogByMobile(string $mobile, string $requestUri): void
    {
        try {
            $user = \app\system\model\User::where('mobile', $mobile)->find();
            if (!$user) return;

            $this->saveLoginLog((int) $user->id, $requestUri);
        } catch (\Throwable) {
            // 日志记录失败不影响登录
        }
    }

    /**
     * 保存登录日志记录
     */
    private function saveLoginLog(int $userId, string $requestUri): void
    {
        LogModel::create([
            'action_type'    => ActionType::LOGIN->value,
            'request_uri'    => $requestUri,
            'request_method' => 'POST',
            'ip'             => $this->request->ip(),
            'status'         => 1,
            'create_by'      => $userId,
            'create_time'    => date('Y-m-d H:i:s'),
        ]);
    }
}
