<?php declare(strict_types=1);

namespace app\Auth\Service;

use app\common\exception\BusinessException;
use app\common\security\JwtTokenManager;
use app\common\web\ResultCode;
use app\System\Model\User;
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
            throw new BusinessException(ResultCode::USER_ERROR, '账号已被禁用');
        }

        // 构建用户认证信息，JwtTokenManager 会自动查询角色和权限
        $userAuthInfo = [
            'userId' => (int) $user->id,
            'deptId' => $user->dept_id ?? null,
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
