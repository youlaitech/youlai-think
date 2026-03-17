<?php declare(strict_types=1);

namespace app\system\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\system\model\Menu;
use think\facade\Db;

/**
 * 菜单服务。
 */
final class MenuService
{
    /**
     * 获取菜单下拉数据
     */
    public function getOptions(bool $onlyParent = false): array
    {
        $query = Menu::where('visible', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc');

        if ($onlyParent) {
            // 只返回目录/菜单，不返回按钮
            $query->whereIn('type', ['C', 'M']);
        }

        $list = $query->select()->toArray();

        return $this->buildOptionTree($list);
    }

    /**
     * 获取菜单树
     */
    public function getTree(): array
    {
        $list = Menu::where('visible', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->buildTree($list);
    }

    /**
     * 获取用户的菜单树（路由用）
     * 注意：visible=0 的隐藏菜单仍需返回路由，只是前端不显示菜单，但可以通过路由跳转访问
     */
    public function getUserMenuTree(int $userId, array $roleCodes): array
    {
        // 超级管理员获取所有菜单
        if (in_array('ROOT', $roleCodes, true)) {
            $menus = Menu::where('type', '<>', 'B') // 排除按钮
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
                ->where('type', '<>', 'B')
                ->order('sort', 'asc')
                ->select()
                ->toArray();
        }

        return $this->buildRoutes(0, $menus);
    }

    /**
     * 根据ID获取菜单详情
     */
    public function getById(int $id): ?array
    {
        return Menu::find($id)?->toArray();
    }

    /**
     * 获取菜单表单数据
     */
    public function getFormById(int $id): ?array
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return null;
        }
        $data = $menu->toArray();
        // params: {k: v} -> [{key: k, value: v}]
        if (!empty($data['params']) && is_array($data['params'])) {
            $data['params'] = array_map(
                fn($k, $v) => ['key' => $k, 'value' => $v],
                array_keys($data['params']),
                array_values($data['params'])
            );
        } else {
            $data['params'] = null;
        }
        return $data;
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
            'type' => $data['type'] ?? 'M',
            'name' => $data['name'],
            'route_path' => $data['route_path'] ?? '',
            'component' => $data['component'] ?? '',
            'perm' => $data['perm'] ?? '',
            'icon' => $data['icon'] ?? '',
            'sort' => $data['sort'] ?? 0,
            'visible' => $data['visible'] ?? 1,
            'params' => $this->transformParams($data['params'] ?? null),
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

        $oldPerm = $menu->perm;
        $newPerm = $data['perm'] ?? $menu->perm;

        $menu->parent_id = $data['parent_id'] ?? $menu->parent_id;
        $menu->type = $data['type'] ?? $menu->type;
        $menu->name = $data['name'] ?? $menu->name;
        $menu->route_path = $data['route_path'] ?? $menu->route_path;
        $menu->component = $data['component'] ?? $menu->component;
        $menu->perm = $newPerm;
        $menu->icon = $data['icon'] ?? $menu->icon;
        $menu->sort = $data['sort'] ?? $menu->sort;
        $menu->visible = $data['visible'] ?? $menu->visible;
        $menu->params = $this->transformParams($data['params'] ?? null);

        $result = $menu->save();

        // perm 变更时刷新相关角色的权限缓存
        if ($result && $oldPerm !== $newPerm) {
            $this->refreshAffectedRolePermsCache($id);
        }

        return $result;
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

    /**
     * 修改菜单显示状态
     */
    public function updateVisible(int $id, int $visible): bool
    {
        $menu = Menu::find($id);
        if (!$menu) {
            throw new BusinessException(ResultCode::USER_ERROR, '菜单不存在');
        }

        $menu->visible = $visible;
        return $menu->save();
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

    private function buildOptionTree(array $list, int $parentId = 0): array
    {
        $options = [];

        foreach ($list as $item) {
            if ((int) ($item['parent_id'] ?? 0) === $parentId) {
                $node = [
                    'value' => (int) $item['id'],
                    'label' => (string) ($item['name'] ?? ''),
                ];

                $children = $this->buildOptionTree($list, (int) $item['id']);
                if (!empty($children)) {
                    $node['children'] = $children;
                }

                $options[] = $node;
            }
        }

        return $options;
    }

    /**
     * 构建路由结构（与 Java RouteVO 兼容）
     */
    private function buildRoutes(int $parentId, array $menuList): array
    {
        $routes = [];

        foreach ($menuList as $menu) {
            if ((int) $menu['parent_id'] === $parentId) {
                $route = $this->toRouteVo($menu);
                $children = $this->buildRoutes((int) $menu['id'], $menuList);
                if (!empty($children)) {
                    $route['children'] = $children;

                    $redirect = $route['redirect'] ?? null;
                    if ($redirect === '' || $redirect === null) {
                        $parentPath = (string) ($route['path'] ?? '');
                        $firstChildPath = (string) ($children[0]['path'] ?? '');
                        if ($parentPath !== '' && $firstChildPath !== '') {
                            $route['redirect'] = rtrim($parentPath, '/') . '/' . ltrim($firstChildPath, '/');
                        }
                    }
                }
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * 将菜单转换为路由对象
     */
    private function toRouteVo(array $menu): array
    {
        $routePath = $menu['route_path'] ?? '';
        $isExternal = str_starts_with($routePath, 'http://') || str_starts_with($routePath, 'https://');

        $menuType = (string) ($menu['type'] ?? 'M');

        // 路由名称
        $routeName = $menu['route_name'] ?? '';
        if (empty($routeName)) {
            if ($isExternal) {
                $routeName = 'ext-' . $menu['id'];
            } elseif ($menuType === 'C' && !empty($routePath)) {
                $routeName = $routePath;
            } else {
                $routeName = $this->toCamelCase($routePath);
            }
        }

        return [
            'path' => $routePath,
            'component' => $isExternal ? null : ($menu['component'] ?? null),
            'redirect' => $menu['redirect'] ?? null,
            'name' => $routeName,
            'meta' => [
                'title' => $menu['name'] ?? '',
                'icon' => $menu['icon'] ?? null,
                'hidden' => ($menu['visible'] ?? 1) === 0,
                'keepAlive' => ($menu['keep_alive'] ?? 0) === 1,
                'alwaysShow' => ($menu['always_show'] ?? 0) === 1,
                'params' => $menu['params'] ?? null,
            ],
        ];
    }

    /**
     * 路径转驼峰命名
     */
    private function toCamelCase(string $path): string
    {
        $path = ltrim($path, '/');
        $parts = explode('-', str_replace('/', '-', $path));
        $result = '';
        foreach ($parts as $i => $part) {
            $result .= $i === 0 ? $part : ucfirst($part);
        }
        return ucfirst($result) ?: 'Route';
    }

    /**
     * 刷新受影响角色的权限缓存
     */
    private function refreshAffectedRolePermsCache(int $menuId): void
    {
        $roleCodes = Db::name('sys_role_menu')
            ->alias('rm')
            ->join('sys_role r', 'rm.role_id = r.id')
            ->where('rm.menu_id', $menuId)
            ->where('r.is_deleted', 0)
            ->column('r.code');

        if (!empty($roleCodes)) {
            (new RolePermService())->refreshRolePermsCacheBatch($roleCodes);
        }
    }

    /**
     * 转换params格式
     * 前端: [{key: "k", value: "v"}] -> 存储: {k: v}
     */
    private function transformParams(?array $params): ?array
    {
        if (empty($params)) {
            return null;
        }
        $result = [];
        foreach ($params as $item) {
            if (isset($item['key'])) {
                $result[$item['key']] = $item['value'] ?? '';
            }
        }
        return $result ?: null;
    }
}
