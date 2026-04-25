<?php declare(strict_types=1);

namespace app\common\middleware;

use app\common\util\CaseConverter;
use think\Response;

/**
 * 数据权限中间件
 * 把 JWT 里的 dataScopes 等信息整理后挂到 request 上
 */
final class DataScopeMiddleware
{
    /**
     * 补充 dataScopes、roles 等字段到 authUser
     */
    public function handle($request, \Closure $next): Response
    {
        $authUser = (array) ($request->__authUser ?? []);
        $userId = (int) ($authUser['userId'] ?? 0);
        if ($userId <= 0) {
            $response = $next($request);
            return $response instanceof Response ? $response : response($response);
        }

        // 将 JWT 中的 camelCase key 转为 snake_case，统一内部数据结构
        $authUser['user_id'] = $userId;
        $authUser['dept_id'] = $authUser['deptId'] ?? null;

        // dataScopes 已经在 JWT 中解析，转为 snake_case
        $dataScopes = $authUser['dataScopes'] ?? [];
        $authUser['data_scopes'] = CaseConverter::toSnakeCase($dataScopes);

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
