<?php declare(strict_types=1);

namespace app\auth\service;

use app\common\exception\BusinessException;
use extend\redis\RedisClient;
use extend\jwt\JwtTokenManager;
use app\common\web\ResultCode;
use app\system\model\User;
use think\facade\Db;

/**
 * 认证服务
 */
final class AuthService
{
    private JwtTokenManager $jwt;

    public function __construct(JwtTokenManager $jwt)
    {
        $this->jwt = $jwt;
    }

    /**
     * 用户登录
     */
    public function login(string $username, string $password): array
    {
        // 查找用户
        $user = User::where('username', $username)->find();

        if (!$user) {
            throw new BusinessException(ResultCode::USER_PASSWORD_ERROR);
        }

        // 验证密码
        if (!password_verify($password, $user->password)) {
            throw new BusinessException(ResultCode::USER_PASSWORD_ERROR);
        }

        // 检查状态
        if ($user->status != 1) {
            throw new BusinessException('账号已被禁用');
        }

        // 查询用户角色
        $roles = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $user->id)
            ->where('r.is_deleted', 0)
            ->where('r.status', 1)
            ->column('r.code');
        $authorities = array_map(fn(string $code) => 'ROLE_' . $code, $roles);

        // 构建用户认证信息
        $userAuthInfo = [
            'userId' => (int) $user->id,
            'deptId' => $user->dept_id ?? null,
            'authorities' => $authorities,
        ];

        // 生成 Token
        $token = $this->jwt->generateToken($userAuthInfo);

        return [
            'accessToken' => $token->accessToken,
            'refreshToken' => $token->refreshToken,
            'tokenType' => $token->tokenType,
            'expiresIn' => $token->expiresIn,
        ];
    }

    /**
     * 发送登录短信验证码
     */
    public function sendSmsLoginCode(string $mobile): void
    {
        // 检查手机号是否已注册
        $user = User::where('mobile', $mobile)->find();
        if (!$user) {
            throw new BusinessException('该手机号未注册');
        }

        // 生成验证码（测试环境固定为1234）
        $code = '1234';

        // TODO: 实际发送短信验证码
        // $templateParams = ['code' => $code];
        // $this->smsService->sendSms($mobile, 'LOGIN', $templateParams);

        // 缓存验证码至Redis，5分钟有效
        $redis = RedisClient::get();
        $key = "sms:login:{$mobile}";
        $redis->setex($key, 300, $code);
    }

    /**
     * 短信验证码登录
     */
    public function loginBySms(string $mobile, string $code): array
    {
        // 验证验证码
        $redis = RedisClient::get();
        $key = "sms:login:{$mobile}";
        $cachedCode = $redis->get($key);

        if (!$cachedCode || $cachedCode !== $code) {
            throw new BusinessException('验证码错误或已过期');
        }

        // 删除验证码
        $redis->del($key);

        // 查找用户
        $user = User::where('mobile', $mobile)->find();
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        // 检查状态
        if ($user->status != 1) {
            throw new BusinessException('账号已被禁用');
        }

        // 查询用户角色
        $roles = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $user->id)
            ->where('r.is_deleted', 0)
            ->where('r.status', 1)
            ->column('r.code');
        $authorities = array_map(fn(string $code) => 'ROLE_' . $code, $roles);

        // 构建用户认证信息
        $userAuthInfo = [
            'userId' => (int) $user->id,
            'deptId' => $user->dept_id ?? null,
            'authorities' => $authorities,
        ];

        // 生成 Token
        $token = $this->jwt->generateToken($userAuthInfo);

        return [
            'accessToken' => $token->accessToken,
            'refreshToken' => $token->refreshToken,
            'tokenType' => $token->tokenType,
            'expiresIn' => $token->expiresIn,
        ];
    }

    /**
     * 登出
     */
    public function logout(?string $accessToken, ?string $refreshToken): void
    {
        $this->jwt->invalidate($accessToken, $refreshToken);
    }

    /**
     * 刷新 Token
     */
    public function refresh(string $refreshToken): array
    {
        $token = $this->jwt->refreshToken($refreshToken);

        return [
            'accessToken' => $token->accessToken,
            'refreshToken' => $token->refreshToken,
            'tokenType' => $token->tokenType,
            'expiresIn' => $token->expiresIn,
        ];
    }
}
