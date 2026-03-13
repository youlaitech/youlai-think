<?php declare(strict_types=1);

namespace app\middleware;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\System\Service\RolePermService;

/**
 * 权限校验中间件
 */
final class PermMiddleware
{
    /**
     * 校验当前用户是否具备指定权限
     */
    public function handle($request, \Closure $next, string $perm = '')
    {
        if ($perm === '') {
            return $next($request);
        }

        $authUser = (array) ($request->__authUser ?? []);
        $userId = (int) ($authUser['userId'] ?? 0);
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $roleCodes = (array) ($authUser['roles'] ?? []);
        if (empty($roleCodes)) {
            throw new BusinessException(ResultCode::ACCESS_PERMISSION_EXCEPTION);
        }

        // ROOT/ADMIN 直接放行
        if (in_array('ROOT', $roleCodes, true) || in_array('ADMIN', $roleCodes, true)) {
            return $next($request);
        }

        // 从缓存获取权限
        $rolePermService = new RolePermService();
        $perms = $rolePermService->getRolePermsByRoleCodes($roleCodes);

        if (!in_array($perm, $perms, true)) {
            throw new BusinessException(ResultCode::ACCESS_PERMISSION_EXCEPTION);
        }

        return $next($request);
    }
}
