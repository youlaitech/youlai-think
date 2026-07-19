<?php declare(strict_types=1);

namespace app\common\traits;

/**
 * 认证用户 Trait
 * 获取当前登录用户的 ID、角色、权限等信息
 */
trait AuthTrait
{
    /**
     * 获取当前认证用户
     */
    protected function getAuthUser(): array
    {
        return (array) ($this->request->authUser ?? []);
    }

    /**
     * 获取当前用户ID
     */
    protected function getAuthUserId(): int
    {
        $authUser = $this->getAuthUser();
        if (isset($authUser['id'])) {
            return (int) $authUser['id'];
        }

        return (int) ($authUser['user_id'] ?? $authUser['userId'] ?? 0);
    }

    /**
     * 获取当前用户名
     */
    protected function getAuthUsername(): string
    {
        return (string) ($this->getAuthUser()['username'] ?? '');
    }

    /**
     * 获取当前用户角色编码列表
     */
    protected function getAuthRoleCodes(): array
    {
        $authUser = $this->getAuthUser();
        // authorities 格式为 ['ROLE_ROOT', 'ROLE_ADMIN']，提取角色代码
        $authorities = (array) ($authUser['authorities'] ?? []);
        return array_map(fn($a) => str_starts_with($a, 'ROLE_') ? substr($a, 5) : $a, $authorities);
    }

    /**
     * 判断是否超级管理员
     */
    protected function isSuperAdmin(): bool
    {
        return in_array('ROOT', $this->getAuthRoleCodes(), true);
    }

    /**
     * 判断是否管理员
     */
    protected function isAdmin(): bool
    {
        $roles = $this->getAuthRoleCodes();

        return in_array('ROOT', $roles, true) || in_array('ADMIN', $roles, true);
    }
}
