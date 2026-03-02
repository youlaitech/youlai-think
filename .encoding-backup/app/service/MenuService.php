<?php declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\Menu;
use think\facade\Db;

/**
 * 菜单服务。
 */
final class MenuService
{
    /**
     * 获取菜单树
     */
    public function getTree(): array
    {
        $list = Menu::where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->buildTree($list);
    }

    /**
     * 获取用户的菜单树（路由用）
     */
    public function getUserMenuTree(int $userId, array $roleCodes): array
    {
        // 超级管理员获取所有菜单
        if (in_array('ROOT', $roleCodes, true)) {
            $menus = Menu::where('status', 1)
                ->whereIn('type', ['catalog', 'menu'])
                ->order('sort', 'asc')
                ->select()
                ->toArray();
        } else {
            // 根据角色获取菜单
            $roleIds = Db::name('sys_user_role')
                ->where('user_id', $userId)
                ->column('role_id');

            $menuIds = Db::name('sys_role_menu')
                ->whereIn('role_id', $roleIds)
                ->column('menu_id');

            $menus = Menu::whereIn('id', $menuIds)
                ->where('status', 1)
                ->whereIn('type', ['catalog', 'menu'])
                ->order('sort', 'asc')
                ->select()
                ->toArray();
        }

        return $this->buildTree($menus);
    }

    /**
     * 根据ID获取菜单详情
     */
    public function getById(int $id): ?array
    {
        return Menu::find($id)?->toArray();
    }

    /**
     * 获取所有菜单（平铺）
     */
    public function getAll(): array
    {
        return Menu::order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 创建菜单
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) Menu::insertGetId([
            'parent_id' => $data['parent_id'] ?? 0,
            'type' => $data['type'] ?? 'menu',
            'name' => $data['name'],
            'path' => $data['path'] ?? '',
            'component' => $data['component'] ?? '',
            'perm' => $data['perm'] ?? '',
            'icon' => $data['icon'] ?? '',
            'sort' => $data['sort'] ?? 0,
            'status' => $data['status'] ?? 1,
            'visible' => $data['visible'] ?? 1,
            'redirect' => $data['redirect'] ?? '',
            'create_time' => $now,
            'update_time' => $now,
        ]);
    }

    /**
     * 更新菜单
     */
    public function update(int $id, array $data): bool
    {
        $menu = Menu::find($id);
        if (!$menu) {
            throw new BusinessException(ResultCode::USER_ERROR, '菜单不存在');
        }

        // 不能把自己设为父级
        if (isset($data['parent_id']) && (int) $data['parent_id'] === $id) {
            throw new BusinessException(ResultCode::USER_ERROR, '父级菜单不能是自己');
        }

        $menu->parent_id = $data['parent_id'] ?? $menu->parent_id;
        $menu->type = $data['type'] ?? $menu->type;
        $menu->name = $data['name'] ?? $menu->name;
        $menu->path = $data['path'] ?? $menu->path;
        $menu->component = $data['component'] ?? $menu->component;
        $menu->perm = $data['perm'] ?? $menu->perm;
        $menu->icon = $data['icon'] ?? $menu->icon;
        $menu->sort = $data['sort'] ?? $menu->sort;
        $menu->status = $data['status'] ?? $menu->status;
        $menu->visible = $data['visible'] ?? $menu->visible;
        $menu->redirect = $data['redirect'] ?? $menu->redirect;

        return $menu->save();
    }

    /**
     * 删除菜单
     */
    public function delete(int $id): bool
    {
        // 检查是否有子菜单
        $childCount = Menu::where('parent_id', $id)->count();
        if ($childCount > 0) {
            throw new BusinessException(ResultCode::USER_ERROR, '存在子菜单，无法删除');
        }

        // 删除关联
        Db::name('sys_role_menu')->where('menu_id', $id)->delete();

        return Menu::destroy($id) > 0;
    }

    // ==================== 私有方法 ====================

    /**
     * 构建树结构
     */
    private function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];

        foreach ($list as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $children = $this->buildTree($list, (int) $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }

        return $tree;
    }
}
