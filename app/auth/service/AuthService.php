<?php declare(strict_types=1);

namespace app\auth\service;

use app\common\exception\BusinessException;
use app\common\util\VerifyCodeHelper;
use extend\jwt\JwtTokenManager;
use app\common\web\ResultCode;
use app\system\model\User;
use think\facade\Db;

/**
 * 认证服务（登录、登出、Token 刷新）
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

        return $this->buildTokenResponse($user);
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

        // 生成验证码并缓存
        VerifyCodeHelper::generateAndCache('sms:login', $mobile);
    }

    /**
     * 短信验证码登录
     */
    public function loginBySms(string $mobile, string $code): array
    {
        // 验证验证码
        if (!VerifyCodeHelper::verify('sms:login', $mobile, $code)) {
            throw new BusinessException('验证码错误或已过期');
        }

        // 查找用户
        $user = User::where('mobile', $mobile)->find();
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        return $this->buildTokenResponse($user);
    }

    /**
     * 构建登录响应（检查状态、查询角色、生成Token）
     */
    private function buildTokenResponse(User $user): array
    {
        if ($user->status != 1) {
            throw new BusinessException('账号已被禁用');
        }

        $roles = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $user->id)
            ->where('r.is_deleted', 0)
            ->where('r.status', 1)
            ->column('r.code');
        $authorities = array_map(fn(string $code) => 'ROLE_' . $code, $roles);

        $token = $this->jwt->generateToken([
            'userId' => (int) $user->id,
            'deptId' => $user->dept_id ?? null,
            'authorities' => $authorities,
        ]);

        return [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'token_type' => $token->tokenType,
            'expires_in' => $token->expiresIn,
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
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'token_type' => $token->tokenType,
            'expires_in' => $token->expiresIn,
        ];
    }
}
