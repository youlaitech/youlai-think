<?php declare(strict_types=1);

namespace app\System\Service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\System\Model\Menu;
use app\System\Model\Role;
use app\System\Model\RoleMenu;
use think\facade\Db;

/**
 * 角色服务
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

        if (array_key_exists('data_scope', $data) && !array_key_exists('dataScope', $data)) {
            $data['dataScope'] = (int) $data['data_scope'];
        }
        $data['menuIds'] = array_column($data['menus'] ?? [], 'id');
        unset($data['menus']);

        // 获取角色关联的部门ID（自定义数据权限用）
        $data['deptIds'] = array_map('strval', Db::name('sys_role_dept')
            ->where('role_id', $id)
            ->column('dept_id'));

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
        $page = (int) ($params['pageNum'] ?? 1);
        $pageSize = min((int) ($params['pageSize'] ?? 10), 100);

        $query = Role::field(self::LIST_FIELDS)
            ->order('sort', 'asc')
            ->order('id', 'desc');

        // 条件筛选
        $this->applyFilters($query, $params);

        $total = $query->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        // 格式化
        foreach ($list as &$item) {
            $item['statusText'] = $item['status'] == 1 ? '启用' : '禁用';

            $dataScope = (int) ($item['data_scope'] ?? 0);
            $item['dataScopeLabel'] = match ($dataScope) {
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
        // 检查编码是否重复
        if (Role::where('code', $data['code'])->find()) {
            throw new BusinessException(ResultCode::USER_ERROR, '角色编码已存在');
        }

        return Db::transaction(function () use ($data) {
            $now = date('Y-m-d H:i:s');

            $roleId = Role::insertGetId([
                'code' => $data['code'],
                'name' => $data['name'],
                'status' => $data['status'] ?? 1,
                'sort' => $data['sort'] ?? 0,
                'data_scope' => $data['dataScope'] ?? 1,
                'remark' => $data['remark'] ?? '',
                'create_time' => $now,
                'update_time' => $now,
            ]);

            // 分配菜单
            if (!empty($data['menuIds'])) {
                $this->assignMenus($roleId, $data['menuIds']);
            }

            // 分配部门（自定义数据权限）
            if (!empty($data['deptIds'])) {
                $this->assignDepts($roleId, $data['deptIds']);
            }

            return (int) $roleId;
        });
    }

    /**
     * 更新角色
     */
    public function update(int $id, array $data): bool
    {
        $role = Role::find($id);
        if (!$role) {
            throw new BusinessException(ResultCode::USER_ERROR, '角色不存在');
        }

        // ROOT 角色不允许修改
        if ($role->code === 'ROOT') {
            throw new BusinessException(ResultCode::USER_ERROR, '超级管理员角色不允许修改');
        }

        return Db::transaction(function () use ($id, $role, $data) {
            $role->name = $data['name'] ?? $role->name;
            $role->status = $data['status'] ?? $role->status;
            $role->sort = $data['sort'] ?? $role->sort;
            $role->data_scope = $data['dataScope'] ?? $role->data_scope;
            $role->remark = $data['remark'] ?? $role->remark;
            $role->save();

            // 更新菜单
            if (isset($data['menuIds'])) {
                $this->syncMenus($id, $data['menuIds']);
            }

            // 更新部门
            if (isset($data['deptIds'])) {
                $this->syncDepts($id, $data['deptIds']);
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
            // 删除角色菜单关联
            RoleMenu::whereIn('role_id', $ids)->delete();

            // 删除角色
            return Role::destroy($ids);
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
        $roleIds = Db::name('sys_user_role')
            ->where('user_id', $userId)
            ->column('role_id');

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
            throw new BusinessException(ResultCode::USER_ERROR, '角色不存在');
        }

        // ROOT 角色不允许修改状态
        if ($role->code === 'ROOT') {
            throw new BusinessException(ResultCode::USER_ERROR, '超级管理员角色不允许修改状态');
        }

        $role->status = $status;
        return $role->save();
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
        return array_map('strval', Db::name('sys_role_dept')
            ->where('role_id', $roleId)
            ->column('dept_id'));
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
            (new RolePermService())->refreshRolePermsCache($role->code);
        }
    }

    /**
     * 同步角色部门权限
     */
    public function syncDepts(int $roleId, array $deptIds): void
    {
        Db::name('sys_role_dept')->where('role_id', $roleId)->delete();

        if (!empty($deptIds)) {
            $this->assignDepts($roleId, $deptIds);
        }
    }

    // ==================== 私有方法 ====================

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

        Db::name('sys_role_dept')->insertAll($data);
    }
}
