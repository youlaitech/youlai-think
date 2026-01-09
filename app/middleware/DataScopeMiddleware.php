<?php

declare(strict_types=1);

namespace app\middleware;

use think\facade\Db;

/**
 * 数据范围中间件
 *
 * 读取当前用户角色数据范围并写入 authUser.dataScope
 */
final class DataScopeMiddleware
{
    /**
     * 写入 authUser.dataScope
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

        $scopes = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->where('r.is_deleted', 0)
            ->column('r.data_scope');

        $scopes = array_values(array_filter(array_map('intval', $scopes), fn($v) => $v > 0));
        $dataScope = empty($scopes) ? 1 : min($scopes);

        $authUser['dataScope'] = $dataScope;
        $request->setAuthUser($authUser);

        return $next($request);
    }
}
