<?php declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\support\redis\RedisClient;
use app\support\security\JwtTokenManager;
use app\common\web\ResultCode;
use app\model\User;
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

        // 获取用户角色和权限
        $roleCodes = $this->getUserRoleCodes((int) $user->id);
        $dataScopes = $this->getUserDataScopes((int) $user->id, $roleCodes);

        // 生成 Token
        $token = $this->jwt->generateAccessToken([
            'id' => $user->id,
            'username' => $user->username,
            'roleCodes' => $roleCodes,
            'dataScopes' => $dataScopes,
        ]);

        $refreshToken = $this->jwt->generateRefreshToken([
            'id' => $user->id,
        ]);

        return [
            'accessToken' => $token,
            'refreshToken' => $refreshToken,
            'tokenType' => 'Bearer',
            'expiresIn' => 7200,
        ];
    }

    /**
     * 登出
     */
    public function logout(string $token): void
    {
        // 将 Token 加入黑名单
        $this->jwt->blacklist($token);
    }

    /**
     * 刷新 Token
     */
    public function refresh(string $refreshToken): array
    {
        $payload = $this->jwt->parseRefreshToken($refreshToken);

        $user = User::find($payload['id']);
        if (!$user) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $roleCodes = $this->getUserRoleCodes((int) $user->id);
        $dataScopes = $this->getUserDataScopes((int) $user->id, $roleCodes);

        $newToken = $this->jwt->generateAccessToken([
            'id' => $user->id,
            'username' => $user->username,
            'roleCodes' => $roleCodes,
            'dataScopes' => $dataScopes,
        ]);

        return [
            'accessToken' => $newToken,
            'tokenType' => 'Bearer',
            'expiresIn' => 7200,
        ];
    }

    /**
     * 获取用户角色编码
     */
    private function getUserRoleCodes(int $userId): array
    {
        $roleIds = Db::name('sys_user_role')
            ->where('user_id', $userId)
            ->column('role_id');

        if (empty($roleIds)) {
            return [];
        }

        return Db::name('sys_role')
            ->whereIn('id', $roleIds)
            ->where('status', 1)
            ->column('code');
    }

    /**
     * 获取用户数据权限范围
     */
    private function getUserDataScopes(int $userId, array $roleCodes): array
    {
        // 超级管理员返回全部权限
        if (in_array('ROOT', $roleCodes, true)) {
            return [[
                'type' => 'ALL',
                'deptIds' => [],
            ]];
        }

        $roleIds = Db::name('sys_user_role')
            ->where('user_id', $userId)
            ->column('role_id');

        if (empty($roleIds)) {
            return [];
        }

        // 获取角色的数据权限配置
        $dataScopes = Db::name('sys_role')
            ->whereIn('id', $roleIds)
            ->where('status', 1)
            ->field(['data_scope', 'scope_dept_ids'])
            ->select()
            ->toArray();

        $result = [];
        foreach ($dataScopes as $scope) {
            $deptIds = $this->resolveDataScope(
                (int) $scope['data_scope'],
                $scope['scope_dept_ids'] ?? '',
                $userId
            );

            $result[] = [
                'type' => $scope['data_scope'],
                'deptIds' => $deptIds,
            ];
        }

        return $result;
    }

    /**
     * 解析数据权限范围
     */
    private function resolveDataScope(int $type, string $scopeDeptIds, int $userId): array
    {
        switch ($type) {
            case 1: // 全部数据
                return [];

            case 2: // 自定义
                return array_map('intval', explode(',', $scopeDeptIds));

            case 3: // 本部门
                $deptId = Db::name('sys_user')->where('id', $userId)->value('dept_id');
                return $deptId ? [(int) $deptId] : [];

            case 4: // 本部门及以下
                $deptId = Db::name('sys_user')->where('id', $userId)->value('dept_id');
                if (!$deptId) {
                    return [];
                }
                // 获取子部门
                return app(DeptService::class)->getDescendantIds((int) $deptId);

            case 5: // 仅本人
                return [];

            default:
                return [];
        }
    }
}
