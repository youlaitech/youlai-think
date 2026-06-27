<?php declare(strict_types=1);

namespace app\common\middleware;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\system\service\RolePermService;
use think\Response;

/**
 * 权限校验中间件
 */
final class PermMiddleware
{
    /**
     * 校验当前用户是否具备指定权限
     */
    public function handle($request, \Closure $next, string $perm = ''): Response
    {
        if ($perm === '') {
            $response = $next($request);
            return $response instanceof Response ? $response : response($response);
        }

        $authUser = (array) ($request->__authUser ?? []);
        $userId = (int) ($authUser['userId'] ?? 0);
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        // authorities 格式为 ['ROLE_ROOT', 'ROLE_ADMIN']，提取角色代码
        $authorities = (array) ($authUser['authorities'] ?? []);
        $roleCodes = array_map(fn($a) => str_starts_with($a, 'ROLE_') ? substr($a, 5) : $a, $authorities);
        if (empty($roleCodes)) {
            throw new BusinessException(ResultCode::ACCESS_PERMISSION_EXCEPTION);
        }

        // ROOT 超级管理员直接放行
        if (in_array('ROOT', $roleCodes, true)) {
            $response = $next($request);
            return $response instanceof Response ? $response : response($response);
        }

        // 从缓存获取权限，Redis/DB 异常兜底按无权限处理
        try {
            $perms = app()->make(RolePermService::class)->getRolePermsByRoleCodes($roleCodes);
        } catch (\Throwable) {
            $perms = [];
        }

        if (!in_array($perm, $perms, true)) {
            throw new BusinessException(ResultCode::ACCESS_PERMISSION_EXCEPTION);
        }

        $response = $next($request);
        return $response instanceof Response ? $response : response($response);
    }
}
