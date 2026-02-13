<?php

declare(strict_types=1);

namespace app\middleware;

/**
 * 数据范围中间件
 *
 * 从 JWT 中读取多角色数据权限列表（dataScopes）
 * 支持多角色数据权限合并（并集策略）
 */
final class DataScopeMiddleware
{
    /**
     * 确保 authUser 中包含 dataScopes
     *
     * @param mixed    $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        if (!($request instanceof \app\Request)) {
            return $next($request);
        }

        $authUser = $request->getAuthUser();
        $userId = (int) ($authUser['userId'] ?? 0);
        if ($userId <= 0) {
            return $next($request);
        }

        // dataScopes 已经在 JWT 中解析，直接使用
        // 如果 JWT 中没有 dataScopes（兼容旧 token），设置为空数组
        if (!isset($authUser['dataScopes'])) {
            $authUser['dataScopes'] = [];
        }

        // 提取 roles 列表便于判断 ROOT 角色
        $authorities = $authUser['authorities'] ?? [];
        $roles = [];
        foreach ($authorities as $auth) {
            if (is_string($auth) && str_starts_with($auth, 'ROLE_')) {
                $roles[] = substr($auth, 5);
            }
        }
        $authUser['roles'] = $roles;

        $request->setAuthUser($authUser);

        return $next($request);
    }
}
