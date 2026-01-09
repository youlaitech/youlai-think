<?php

declare(strict_types=1);

namespace app\middleware;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * 权限校验中间件
 *
 * 路由上用 middleware('perm:xxx') 传入权限标识
 * ROOT/ADMIN 直接放行
 */
final class PermMiddleware
{
    /**
     * 校验当前用户是否具备指定权限
     *
     * @param mixed    $request
     * @param \Closure $next
     * @param string   $perm
     *
     * @return mixed
     * @throws BusinessException 无权限或认证信息异常时抛出
     */
    public function handle($request, \Closure $next, string $perm = '')
    {
        if ($perm === '') {
            return $next($request);
        }

        if (!($request instanceof \app\Request)) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR);
        }

        $authUser = $request->getAuthUser();
        $userId = (int) ($authUser['userId'] ?? 0);
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        // 超级管理员角色直接放行
        $roleCodes = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->where('r.is_deleted', 0)
            ->column('r.code');
        $roleCodes = array_values(array_unique(array_filter($roleCodes, fn($v) => $v !== null && $v !== '')));

        if (in_array('ROOT', $roleCodes, true) || in_array('ADMIN', $roleCodes, true)) {
            return $next($request);
        }

        $perms = Db::name('sys_role_menu')
            ->alias('rm')
            ->join('sys_user_role ur', 'rm.role_id = ur.role_id')
            ->join('sys_menu m', 'rm.menu_id = m.id')
            ->where('ur.user_id', $userId)
            ->where('m.is_deleted', 0)
            ->where('m.perm', '<>', '')
            ->where('m.perm', 'not null')
            ->column('m.perm');
        $perms = array_values(array_unique(array_filter($perms, fn($v) => $v !== null && $v !== '')));

        if (!in_array($perm, $perms, true)) {
            throw new BusinessException(ResultCode::ACCESS_PERMISSION_EXCEPTION);
        }

        return $next($request);
    }
}
