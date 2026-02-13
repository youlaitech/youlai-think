<?php

declare(strict_types=1);

namespace app\service;

use app\common\enums\DataScopeEnum;
use think\facade\Db;

/**
 * 数据权限服务
 * 支持多角色数据权限合并（并集策略）
 */
class DataPermissionService
{
    /**
     * 应用数据权限过滤
     *
     * @param object $query 查询构建器
     * @param string $deptIdColumn 部门ID字段名（如 'u.dept_id'）
     * @param string $userIdColumn 用户ID字段名（如 'u.id' 或 'u.create_by'）
     * @param array $authUser 当前用户信息（包含 userId、deptId、dataScopes、roles）
     * @return object 过滤后的查询构建器
     */
    public function apply(object $query, string $deptIdColumn, string $userIdColumn, array $authUser): object
    {
        // 超级管理员跳过过滤
        if ($this->isRoot($authUser)) {
            return $query;
        }

        $dataScopes = $authUser['dataScopes'] ?? [];
        $userId = (int) ($authUser['userId'] ?? 0);
        $deptId = $authUser['deptId'] ?? null;
        $deptId = $deptId === null || $deptId === '' ? null : (int) $deptId;

        // 没有数据权限配置，默认只能查看本人数据
        if (empty($dataScopes)) {
            return $userId > 0 ? $query->where($userIdColumn, $userId) : $query->whereRaw('1 = 0');
        }

        // 如果任一角色是 ALL，则跳过数据权限过滤
        if ($this->hasAllDataScope($dataScopes)) {
            return $query;
        }

        // 多角色数据权限合并（并集策略）
        return $this->applyWithDataScopes($query, $deptIdColumn, $userIdColumn, $dataScopes, $userId, $deptId);
    }

    /**
     * 判断是否为超级管理员
     */
    private function isRoot(array $authUser): bool
    {
        $roles = $authUser['roles'] ?? [];
        return in_array('ROOT', $roles, true);
    }

    /**
     * 判断是否包含"全部数据"权限
     */
    private function hasAllDataScope(array $dataScopes): bool
    {
        foreach ($dataScopes as $scope) {
            $dataScope = (int) ($scope['dataScope'] ?? 0);
            if ($dataScope === DataScopeEnum::ALL) {
                return true;
            }
        }
        return false;
    }

    /**
     * 应用多角色数据权限（并集策略）
     */
    private function applyWithDataScopes(
        object $query,
        string $deptIdColumn,
        string $userIdColumn,
        array $dataScopes,
        int $userId,
        ?int $deptId
    ): object {
        $conditions = [];
        $bindings = [];

        foreach ($dataScopes as $scope) {
            $dataScope = (int) ($scope['dataScope'] ?? 0);
            $customDeptIds = $scope['customDeptIds'] ?? null;

            $condition = $this->buildRoleCondition($dataScope, $deptIdColumn, $userIdColumn, $userId, $deptId, $customDeptIds, $bindings);
            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        if (empty($conditions)) {
            return $query->whereRaw('1 = 0');
        }

        // 使用 OR 连接各角色条件（并集）
        $orCondition = '(' . implode(' OR ', $conditions) . ')';
        return $query->whereRaw($orCondition, $bindings);
    }

    /**
     * 构建单个角色的数据权限条件
     */
    private function buildRoleCondition(
        int $dataScope,
        string $deptIdColumn,
        string $userIdColumn,
        int $userId,
        ?int $deptId,
        ?array $customDeptIds,
        array &$bindings
    ): ?string {
        switch ($dataScope) {
            case DataScopeEnum::ALL:
                return null;

            case DataScopeEnum::DEPT:
                if ($deptId === null || $deptId <= 0) {
                    return null;
                }
                $bindings[] = $deptId;
                return "{$deptIdColumn} = ?";

            case DataScopeEnum::SELF:
                if ($userId <= 0) {
                    return null;
                }
                $bindings[] = $userId;
                return "{$userIdColumn} = ?";

            case DataScopeEnum::DEPT_AND_SUB:
                if ($deptId === null || $deptId <= 0) {
                    return null;
                }
                $deptIds = $this->getDeptAndSubIds($deptId);
                if (empty($deptIds)) {
                    $bindings[] = $deptId;
                    return "{$deptIdColumn} = ?";
                }
                $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
                foreach ($deptIds as $id) {
                    $bindings[] = $id;
                }
                return "{$deptIdColumn} IN ({$placeholders})";

            case DataScopeEnum::CUSTOM:
                if (empty($customDeptIds)) {
                    return '1 = 0';
                }
                $placeholders = implode(',', array_fill(0, count($customDeptIds), '?'));
                foreach ($customDeptIds as $id) {
                    $bindings[] = (int) $id;
                }
                return "{$deptIdColumn} IN ({$placeholders})";

            default:
                // 默认本人数据
                if ($userId <= 0) {
                    return null;
                }
                $bindings[] = $userId;
                return "{$userIdColumn} = ?";
        }
    }

    /**
     * 获取部门及子部门ID列表
     */
    private function getDeptAndSubIds(int $deptId): array
    {
        $deptIdStr = (string) $deptId;

        $deptIds = Db::name('sys_dept')
            ->where('is_deleted', 0)
            ->where(function ($query) use ($deptId, $deptIdStr) {
                $query->where('id', $deptId)
                    ->whereOr('tree_path', $deptIdStr)
                    ->whereOrRaw("FIND_IN_SET(?, tree_path)", [$deptIdStr]);
            })
            ->column('id');

        return array_values(array_filter(array_map('intval', $deptIds), fn($v) => $v > 0));
    }

    /**
     * 判断用户是否有全部数据权限
     */
    public function hasAllPermission(array $authUser): bool
    {
        if ($this->isRoot($authUser)) {
            return true;
        }

        $dataScopes = $authUser['dataScopes'] ?? [];
        return $this->hasAllDataScope($dataScopes);
    }
}
