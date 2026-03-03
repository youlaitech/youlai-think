<?php declare(strict_types=1);

namespace app\Auth\Controller;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\controller\BaseController;
use app\Auth\Service\AuthService;
use Gregwar\Captcha\CaptchaBuilder;
use OpenApi\Annotations as OA;
use think\response\Json;

/**
 * @OA\Tag(name="01.认证接口")
 */
final class AuthController extends BaseController
{
    /**
     * 获取验证码
     *
     * @OA\Get(
     *     path="/api/v1/auth/captcha",
     *     summary="获取验证码",
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function captcha(): Json
    {
        // 生成随机验证码短语
        $phrase = '';
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 排除易混淆字符
        for ($i = 0; $i < 4; $i++) {
            $phrase .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $builder = new CaptchaBuilder($phrase);

        // 设置验证码样式
        $builder->setBackgroundColor(255, 255, 255);
        $builder->build(100, 32);

        $uuid = uniqid('', true);

        // 存储验证码到 Redis（5分钟过期）
        \app\common\redis\RedisClient::get()->setex(
            "captcha:{$uuid}",
            300,
            strtolower($phrase) // 存储小写，验证时不区分大小写
        );

        return $this->success([
            'captchaId' => $uuid,
            'captchaBase64' => $builder->inline(),
        ]);
    }

    /**
     * 登录
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
     */
    public function logout(): Json
    {
        $accessToken = $this->request->header('Authorization', '');

        $this->service(AuthService::class)->logout($accessToken ?: null, null);

        return $this->success(null, '登出成功');
    }

    /**
     * 刷新 Token
     */
    public function refresh(): Json
    {
        $refreshToken = $this->getParam('refreshToken', '');

        $result = $this->service(AuthService::class)->refresh($refreshToken);

        return $this->success($result);
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

        $redis = \app\common\redis\RedisClient::get();
        $key = "captcha:{$captchaId}";
        $storedCode = $redis->get($key);

        if (!$storedCode || strtolower($storedCode) !== strtolower($captchaCode)) {
            throw new BusinessException(ResultCode::USER_ERROR, '验证码错误');
        }

        // 删除已使用的验证码
        $redis->del($key);
    }
}
