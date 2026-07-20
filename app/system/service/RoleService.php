<?php declare(strict_types=1);

namespace app\system\service;

use app\common\enums\DataScopeEnum;
use app\common\exception\BusinessException;
use app\common\util\PageUtil;
use app\system\model\Menu;
use app\system\model\Role;
use app\system\model\RoleDept;
use app\system\model\RoleMenu;
use app\system\model\UserRole;
use think\facade\Db;

/**
 * 角色与菜单绑定服务
 */
final class RoleService
{
    /**
     * 角色字段列表
     */
    private const LIST_FIELDS = [
        'id', 'code', 'name', 'status', 'sort', 'data_scope', 'create_time',
    ];

    /**
     * 根据ID获取角色详情
     */
    public function getById(int $id): ?array
    {
        $role = Role::with(['menus'])->find($id);

        if (!$role) {
            return null;
        }

        $data = $role->toArray();

        $data['menu_ids'] = array_column($data['menus'] ?? [], 'id');
        unset($data['menus']);

        // 获取角色关联的部门ID（自定义数据权限用）
        $data['dept_ids'] = array_map('strval', RoleDept::where('role_id', $id)->column('dept_id'));

        return $data;
    }

    /**
     * 根据编码获取角色
     */
    public function getByCode(string $code): ?array
    {
        return Role::where('code', $code)->find()?->toArray();
    }

    /**
     * 分页查询角色列表
     */
    public function paginate(array $params): array
    {
        [$page, $pageSize] = PageUtil::resolve($params);

        $query = Role::field(self::LIST_FIELDS)
            ->order('sort', 'asc')
            ->order('id', 'desc');

        // 条件筛选
        $this->applyFilters($query, $params);

        $total = $query->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        // 格式化
        foreach ($list as &$item) {
            $item['status_text'] = $item['status'] == 1 ? '启用' : '禁用';

            $dataScope = (int) ($item['data_scope'] ?? 0);
            $item['data_scope_label'] = match ($dataScope) {
                1 => '全部数据',
                2 => '部门及子部门数据',
                3 => '本部门数据',
                4 => '本人数据',
                5 => '自定义部门数据',
                default => '',
            };
        }

        return [$list, $total];
    }

    /**
     * 获取所有启用的角色
     */
    public function getAllEnabled(): array
    {
        $list = Role::where('status', 1)
            ->field(['id', 'name'])
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        return array_map(static fn ($item) => [
            'value' => (string) ($item['id'] ?? ''),
            'label' => (string) ($item['name'] ?? ''),
        ], $list);
    }

    /**
     * 创建角色
     */
    public function create(array $data): int
    {
        $name = $data['name'] ?? '';
        $code = $data['code'] ?? '';

        $byCode = Role::where('code', $code)->where('is_deleted', 0)->find();
        $byName = Role::where('name', $name)->where('is_deleted', 0)->find();

        if ($byCode || $byName) {
            throw new BusinessException('角色名称或角色编码已存在');
        }

        // 清理同编码的软删除记录，避免唯一索引冲突
        if ($code !== '') {
            $trashed = Role::where('code', $code)->where('is_deleted', 1)->find();
            if ($trashed) {
                \think\facade\Db::table('sys_role')->where('id', $trashed->id)->delete();
            }
        }

        try {
            return Db::transaction(function () use ($data) {
                $roleId = Role::insertGetId([
                    'code'       => $data['code'],
                    'name'       => $data['name'],
                    'status'     => $data['status'] ?? 1,
                    'sort'       => $data['sort'] ?? 0,
                    'data_scope' => $data['data_scope'] ?? 1,
                    'is_deleted' => 0,
                ]);

                if (!empty($data['menu_ids'])) {
                    $this->assignMenus($roleId, $data['menu_ids']);
                }
                if (!empty($data['dept_ids'])) {
                    $this->assignDepts($roleId, $data['dept_ids']);
                }

                return (int) $roleId;
            });
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BusinessException('角色创建失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新角色
     */
    public function update(int $id, array $data): bool
    {
        $role = Role::find($id);
        if (!$role) {
            throw new BusinessException('角色不存在');
        }

        // ROOT 角色不允许修改
        if ($role->code === 'ROOT') {
            throw new BusinessException('超级管理员角色不允许修改');
        }

        return Db::transaction(function () use ($id, $role, $data) {
            $role->name = $data['name'] ?? $role->name;
            $role->status = $data['status'] ?? $role->status;
            $role->sort = $data['sort'] ?? $role->sort;
            $role->data_scope = $data['data_scope'] ?? $role->data_scope;
            $role->remark = $data['remark'] ?? $role->remark;
            $role->save();

            // 更新菜单
            if (isset($data['menu_ids'])) {
                $this->syncMenus($id, $data['menu_ids']);
            }

            // 更新部门
            if (isset($data['dept_ids'])) {
                $this->syncDepts($id, $data['dept_ids']);
            }

            return true;
        });
    }

    /**
     * 批量删除角色
     */
    public function deleteByIds(array $ids): int
    {
        // 不允许删除系统内置角色
        $protectedCodes = ['ROOT', 'ADMIN'];
        $protectedIds = Role::whereIn('code', $protectedCodes)->column('id');
        $ids = array_diff($ids, $protectedIds);

        if (empty($ids)) {
            return 0;
        }

        return Db::transaction(function () use ($ids) {
            RoleMenu::whereIn('role_id', $ids)->delete();

            $roles = Role::whereIn('id', $ids)->select();
            foreach ($roles as $role) {
                $role->softDelete();
            }
            return count($ids);
        });
    }

    /**
     * 获取角色的菜单权限标识
     */
    public function getPermissionsByRoleId(int $roleId): array
    {
        $role = Role::with(['menus'])->find($roleId);

        if (!$role) {
            return [];
        }

        return array_filter(
            array_column($role->menus->toArray() ?? [], 'perm'),
            fn ($perm) => !empty($perm)
        );
    }

    /**
     * 根据用户ID获取权限标识
     */
    public function getPermissionsByUserId(int $userId): array
    {
        // 获取用户所有角色
        $roleIds = UserRole::where('user_id', $userId)->column('role_id');

        if (empty($roleIds)) {
            return [];
        }

        // 获取所有角色的菜单ID
        $menuIds = RoleMenu::whereIn('role_id', $roleIds)->column('menu_id');

        if (empty($menuIds)) {
            return [];
        }

        // 获取权限标识
        return Menu::whereIn('id', $menuIds)
            ->where('type', 'B')
            ->where('visible', 1)
            ->column('perm');
    }

    /**
     * 修改角色状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $role = Role::find($id);
        if (!$role) {
            throw new BusinessException('角色不存在');
        }

        // ROOT 角色不允许修改状态
        if ($role->code === 'ROOT') {
            throw new BusinessException('超级管理员角色不允许修改状态');
        }

        $role->status = $status;
        $result = $role->save();

        // 状态变更时刷新权限缓存
        if ($result) {
            app()->make(RolePermService::class)->refreshRolePermsCache($role->code);
        }

        return $result;
    }

    /**
     * 获取角色的菜单ID集合
     */
    public function getMenuIds(int $roleId): array
    {
        return RoleMenu::where('role_id', $roleId)->column('menu_id');
    }

    /**
     * 获取角色的部门ID集合(自定义数据权限)
     */
    public function getDeptIds(int $roleId): array
    {
        return array_map('strval', RoleDept::where('role_id', $roleId)->column('dept_id'));
    }

    /**
     * 同步角色菜单权限
     */
    public function syncMenus(int $roleId, array $menuIds): void
    {
        RoleMenu::where('role_id', $roleId)->delete();

        if (!empty($menuIds)) {
            $this->assignMenus($roleId, $menuIds);
        }

        // 刷新角色权限缓存
        $role = Role::find($roleId);
        if ($role && $role->code) {
            app()->make(RolePermService::class)->refreshRolePermsCache($role->code);
        }
    }

    /**
     * 同步角色部门权限
     */
    public function syncDepts(int $roleId, array $deptIds): void
    {
        RoleDept::where('role_id', $roleId)->delete();

        if (!empty($deptIds)) {
            $this->assignDepts($roleId, $deptIds);
        }
    }

    /**
     * 获取用户所有角色的数据权限列表（多角色并集策略）
     *
     * 返回结构：[{ dataScope: int, customDeptIds: ?array }, ...]
     * 用于写入 JWT，在接口层做数据权限过滤。
     */
    public function getRoleDataScopes(array $roleCodes): array
    {
        if (empty($roleCodes)) {
            return [];
        }

        $rows = Role::whereIn('code', $roleCodes)
            ->where('is_deleted', 0)
            ->where('status', 1)
            ->field('code, data_scope')
            ->select()
            ->toArray();

        $scopes = [];
        foreach ($rows as $row) {
            $code = $row['code'];
            $dataScope = (int) ($row['data_scope'] ?? 0);

            if ($dataScope === DataScopeEnum::CUSTOM->value) {
                $roleId = Role::where('code', $code)->where('is_deleted', 0)->value('id');
                $deptIds = [];
                if ($roleId) {
                    $deptIds = array_values(array_filter(array_map(
                        'intval',
                        RoleDept::where('role_id', $roleId)->column('dept_id')
                    ), fn($v) => $v > 0));
                }
                $scopes[] = ['dataScope' => $dataScope, 'customDeptIds' => $deptIds];
            } else {
                $scopes[] = ['dataScope' => $dataScope, 'customDeptIds' => null];
            }
        }

        return $scopes;
    }

    private function applyFilters($query, array $params): void
    {
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', "%{$keyword}%")
                  ->whereOr('code', 'like', "%{$keyword}%");
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
    }

    private function assignMenus(int $roleId, array $menuIds): void
    {
        $data = array_map(fn ($menuId) => [
            'role_id' => $roleId,
            'menu_id' => $menuId,
        ], $menuIds);

        RoleMenu::insertAll($data);
    }

    private function assignDepts(int $roleId, array $deptIds): void
    {
        $data = array_map(fn ($deptId) => [
            'role_id' => $roleId,
            'dept_id' => $deptId,
        ], $deptIds);

        (new RoleDept())->insertAll($data);
    }
}
