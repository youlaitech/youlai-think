<?php declare(strict_types=1);

namespace app\system\service;

use app\system\model\RoleMenu;
use extend\redis\RedisClient;

/**
 * 角色权限缓存服务
 *
 * 缓存用 Redis Hash，field 是 roleCode，value 是权限 JSON
 * 缓存未命中时查库并回写
 */
final class RolePermService
{
    private const CACHE_KEY = 'system:role:perms';

    /**
     * 获取角色权限集合（带缓存）
     */
    public function getRolePermsByRoleCodes(array $roleCodes): array
    {
        if (empty($roleCodes)) {
            return [];
        }

        $redis = RedisClient::get();
        $perms = [];
        $missingRoles = [];

        // 从缓存获取，未命中的角色记录下来
        foreach ($roleCodes as $roleCode) {
            $cached = $redis->hGet(self::CACHE_KEY, $roleCode);
            if ($cached !== false && $cached !== null) {
                $rolePerms = json_decode($cached, true) ?: [];
                foreach ($rolePerms as $perm) {
                    $perms[$perm] = true;
                }
            } else {
                $missingRoles[] = $roleCode;
            }
        }

        // 回源DB并回写缓存，空权限也要写防止穿透
        if (!empty($missingRoles)) {
            $rolePermsMap = $this->getRolePermsFromDatabase($missingRoles);
            foreach ($missingRoles as $roleCode) {
                $rolePerms = $rolePermsMap[$roleCode] ?? [];
                foreach ($rolePerms as $perm) {
                    $perms[$perm] = true;
                }
                $redis->hSet(self::CACHE_KEY, $roleCode, json_encode($rolePerms));
            }
        }

        return array_keys($perms);
    }

    /**
     * 刷新单个角色的权限缓存（角色菜单变更后调用）
     */
    public function refreshRolePermsCache(string $roleCode): void
    {
        $redis = RedisClient::get();
        $redis->hDel(self::CACHE_KEY, $roleCode);

        $rolePerms = $this->getRolePermsFromDatabase([$roleCode])[$roleCode] ?? [];
        $redis->hSet(self::CACHE_KEY, $roleCode, json_encode($rolePerms));
    }

    /**
     * 批量刷新角色权限缓存（菜单变更后调用）
     */
    public function refreshRolePermsCacheBatch(array $roleCodes): void
    {
        if (empty($roleCodes)) {
            return;
        }

        $redis = RedisClient::get();
        foreach ($roleCodes as $roleCode) {
            $redis->hDel(self::CACHE_KEY, $roleCode);
        }

        $rolePermsMap = $this->getRolePermsFromDatabase($roleCodes);
        foreach ($roleCodes as $roleCode) {
            $rolePerms = $rolePermsMap[$roleCode] ?? [];
            $redis->hSet(self::CACHE_KEY, $roleCode, json_encode($rolePerms));
        }
    }

    /**
     * 从数据库查询角色权限
     */
    private function getRolePermsFromDatabase(array $roleCodes): array
    {
        if (empty($roleCodes)) {
            return [];
        }

        $rows = RoleMenu::alias('rm')
            ->join('sys_role r', 'rm.role_id = r.id')
            ->join('sys_menu m', 'rm.menu_id = m.id')
            ->whereIn('r.code', $roleCodes)
            ->where('r.is_deleted', 0)
            ->where('r.status', 1)
            ->where('m.type', 'B')
            ->where('m.perm', '<>', '')
            ->where('m.perm', 'not null')
            ->field('r.code as role_code, m.perm')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $roleCode = $row['role_code'];
            $perm = $row['perm'];
            if (!isset($result[$roleCode])) {
                $result[$roleCode] = [];
            }
            $result[$roleCode][] = $perm;
        }

        // 确保所有角色都有条目，空权限返回空数组
        foreach ($roleCodes as $roleCode) {
            if (!isset($result[$roleCode])) {
                $result[$roleCode] = [];
            }
        }

        return $result;
    }
}
