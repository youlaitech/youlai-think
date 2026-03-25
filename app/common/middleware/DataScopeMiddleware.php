<?php declare(strict_types=1);

namespace app\common\middleware;

use think\Response;

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
     */
    public function handle($request, \Closure $next): Response
    {
        $authUser = (array) ($request->__authUser ?? []);
        $userId = (int) ($authUser['userId'] ?? 0);
        if ($userId <= 0) {
            $response = $next($request);
            return $response instanceof Response ? $response : response($response);
        }

        // dataScopes 已经在 JWT 中解析，直接使用
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

        $request->__authUser = $authUser;

        $response = $next($request);
        return $response instanceof Response ? $response : response($response);
    }
}
