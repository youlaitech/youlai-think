<?php declare(strict_types=1);

namespace app\controller;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\AuthService;
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
        $builder = new CaptchaBuilder();
        $builder->build(120, 40);

        $uuid = uniqid('', true);

        // 存储验证码到 Redis（5分钟过期）
        \app\common\redis\RedisClient::get()->setex(
            "captcha:{$uuid}",
            300,
            $builder->getPhrase()
        );

        return $this->success([
            'uuid' => $uuid,
            'img' => $builder->inline(),
        ]);
    }

    /**
     * 登录
     */
    public function login(): Json
    {
        $username = $this->getParam('username', '');
        $password = $this->getParam('password', '');
        $uuid = $this->getParam('uuid', '');
        $code = $this->getParam('code', '');

        // 验证码校验
        $this->validateCaptcha($uuid, $code);

        // 执行登录
        $result = $this->service(AuthService::class)->login($username, $password);

        return $this->success($result, '登录成功');
    }

    /**
     * 登出
     */
    public function logout(): Json
    {
        $token = $this->request->header('Authorization', '');

        if ($token) {
            $this->service(AuthService::class)->logout($token);
        }

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
    private function validateCaptcha(string $uuid, string $code): void
    {
        if (empty($uuid) || empty($code)) {
            throw new BusinessException(ResultCode::USER_REQUEST_PARAMETER_ERROR, '验证码不能为空');
        }

        $redis = \app\common\redis\RedisClient::get();
        $key = "captcha:{$uuid}";
        $storedCode = $redis->get($key);

        if (!$storedCode || strtolower($storedCode) !== strtolower($code)) {
            throw new BusinessException(ResultCode::USER_ERROR, '验证码错误');
        }

        // 删除已使用的验证码
        $redis->del($key);
    }
}
