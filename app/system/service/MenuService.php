<?php declare(strict_types=1);

namespace app\system\service;

use app\common\exception\BusinessException;
use app\system\model\Menu;
use app\system\model\RoleMenu;
use app\system\model\UserRole;
use think\facade\Db;

/**
 * 菜单路由与权限服务
 */
final class MenuService
{
    /**
     * 获取菜单下拉数据
     */
    public function getOptions(bool $onlyParent = false): array
    {
        $query = Menu::order('sort', 'asc')
            ->order('id', 'asc');

        if ($onlyParent) {
            // 排除按钮（包含目录/菜单/外链）
            $query->where('type', '<>', 'B');
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
     * 用户路由菜单树（隐藏菜单 visible=0 仍返回路由，仅不在菜单栏展示）
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
            $roleIds = UserRole::where('user_id', $userId)->column('role_id');

            $menuIds = RoleMenu::whereIn('role_id', $roleIds)->column('menu_id');

            // 过滤平台管理目录（/support）及其子菜单
            $supportMenuId = Menu::where('route_path', '/support')
                ->where('parent_id', 0)
                ->where('type', 'C')
                ->value('id');
            if ($supportMenuId) {
                $excludeIds = Db::name('sys_menu')
                    ->where('id', $supportMenuId)
                    ->whereOr('tree_path', 'LIKE', '%,' . $supportMenuId . ',%')
                    ->whereOr('tree_path', 'LIKE', '%,' . $supportMenuId)
                    ->whereOr('tree_path', 'LIKE', $supportMenuId . ',%')
                    ->column('id');
                $menuIds = array_diff($menuIds, $excludeIds);
            }

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
        // params 格式转换：{k: v} -> [{key: k, value: v}]
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
     * 获取菜单列表（树形结构，管理页面用）
     */
    public function getAll(): array
    {
        $list = Menu::order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->buildTree($list);
    }

    /**
     * 创建菜单
     */
    public function create(array $data): int
    {
        $parentId = (int) ($data['parent_id'] ?? 0);
        $treePath = $this->generateMenuTreePath($parentId);

        $menuId = (int) Menu::insertGetId([
            'parent_id' => $parentId,
            'type' => $data['type'] ?? 'M',
            'name' => $data['name'],
            'route_name' => $data['route_name'] ?? '',
            'route_path' => $data['route_path'] ?? '',
            'component' => $data['component'] ?? ($data['type'] === 'C' ? 'Layout' : ''),
            'perm' => $data['perm'] ?? '',
            'icon' => $data['icon'] ?? '',
            'sort' => $data['sort'] ?? 0,
            'visible' => $data['visible'] ?? 1,
            'always_show' => $data['always_show'] ?? 0,
            'keep_alive' => $data['keep_alive'] ?? 0,
            'tree_path' => $treePath,
            'params' => $this->transformParams($data['params'] ?? null),
        ]);

        return $menuId;
    }

    /**
     * 更新菜单
     */
    public function update(int $id, array $data): bool
    {
        $menu = Menu::find($id);
        if (!$menu) {
            throw new BusinessException('菜单不存在');
        }

        // 不能把自己设为父级
        if (isset($data['parent_id']) && (int) $data['parent_id'] === $id) {
            throw new BusinessException('父级菜单不能是自己');
        }

        $oldPerm = $menu->perm;
        $newPerm = $data['perm'] ?? $menu->perm;

        // 检查父级是否变更
        $parentChanged = isset($data['parent_id']) && (int) $data['parent_id'] !== (int) $menu->parent_id;

        // 非 clearable 字段：?? 语义（null / 未传 → 保留旧值）
        $menu->parent_id = $data['parent_id'] ?? $menu->parent_id;
        $menu->type = $data['type'] ?? $menu->type;
        $menu->name = $data['name'] ?? $menu->name;
        $menu->route_path = $data['route_path'] ?? $menu->route_path;
        $menu->perm  = $newPerm;
        $menu->sort = $data['sort'] ?? $menu->sort;
        $menu->visible = $data['visible'] ?? $menu->visible;
        $menu->always_show = $data['always_show'] ?? $menu->always_show;
        $menu->keep_alive = $data['keep_alive'] ?? $menu->keep_alive;

        // clearable 字段：array_key_exists 判存在性（null 也写），前端丢 key 才保留旧值
        foreach (['component', 'icon', 'redirect', 'route_name', 'perm', 'external_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $menu->$field = $data[$field];
            }
        }
        // 目录类型且前端未显式传 component → 强制 Layout
        if (($menu->type) === 'C' && !array_key_exists('component', $data)) {
            $menu->component = 'Layout';
        }

        $menu->params = $this->transformParams($data['params'] ?? null);

        // 父级变更时更新 tree_path
        if ($parentChanged) {
            $menu->tree_path = $this->generateMenuTreePath((int) $menu->parent_id);
        }

        $result = $menu->save();

        // 父级变更时递归更新子菜单的 tree_path
        if ($result && $parentChanged) {
            $this->updateChildrenTreePath($id, $menu->tree_path . ',' . $id);
        }

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
        $menu = Menu::find($id);
        if (!$menu) {
            throw new BusinessException('菜单不存在');
        }

        // 检查是否有子菜单
        $childCount = Menu::where('parent_id', $id)->count();
        if ($childCount > 0) {
            throw new BusinessException('存在子菜单，无法删除');
        }

        // 删除关联
        RoleMenu::where('menu_id', $id)->delete();

        // 物理删除（sys_menu 表无 is_deleted 字段）
        return $menu->delete();
    }

    /**
     * 修改菜单显示状态
     */
    public function updateVisible(int $id, int $visible): bool
    {
        $menu = Menu::find($id);
        if (!$menu) {
            throw new BusinessException('菜单不存在');
        }

        $menu->visible = $visible;
        return $menu->save();
    }

    /**
     * 生成菜单 tree_path
     */
    private function generateMenuTreePath(int $parentId): string
    {
        if ($parentId === 0) {
            return '0';
        }
        $parent = Menu::find($parentId);
        return $parent ? ($parent->tree_path ?? '0') . ',' . $parentId : '0,' . $parentId;
    }

    /**
     * 递归更新子菜单的 tree_path
     */
    private function updateChildrenTreePath(int $parentId, string $parentTreePath): void
    {
        $children = Menu::where('parent_id', $parentId)->select();
        foreach ($children as $child) {
            $child->tree_path = $parentTreePath;
            $child->save();
            $this->updateChildrenTreePath((int) $child->id, $parentTreePath . ',' . $child->id);
        }
    }

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

    /**
     * 构建下拉选项树（value/label 格式）
     */
    private function buildOptionTree(array $list, int $parentId = 0): array
    {
        $options = [];

        foreach ($list as $item) {
            if ((int) ($item['parent_id'] ?? 0) === $parentId) {
                $node = [
                    'value' => (string) $item['id'],
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
     * 构建路由结构
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
        $externalUrl = $menu['external_url'] ?? null;
        $menuType = (string) ($menu['type'] ?? 'M');

        $isExternal = $menuType === 'E';
        $isEmbedded = $isExternal && ($menu['component'] ?? '') === 'iframe';

        $path = $isExternal && !$isEmbedded && !empty($externalUrl)
            ? $externalUrl
            : $routePath;

        // 路由名称
        $routeName = $menu['route_name'] ?? '';
        if (empty($routeName) && !$isExternal) {
            if ($menuType === 'C' && !empty($path)) {
                $routeName = $path;
            } else {
                $routeName = $this->toCamelCase($path);
            }
        }

        $meta = [
            'title' => $menu['name'] ?? '',
            'icon' => $menu['icon'] ?? null,
            'hidden' => ($menu['visible'] ?? 1) === 0,
            'keep_alive' => ($menuType === 'M' || $isEmbedded) && ($menu['keep_alive'] ?? 0) === 1,
            'always_show' => ($menu['always_show'] ?? 0) === 1,
            'params' => $menu['params'] ?? null,
        ];

        if ($isEmbedded && !empty($externalUrl)) {
            $meta['externalUrl'] = $externalUrl;
        }

        return [
            'path' => $path,
            'component' => $isEmbedded ? 'iframe' : ($isExternal ? null : ($menu['component'] ?? null)),
            'redirect' => $menu['redirect'] ?? null,
            'name' => $routeName,
            'meta' => $meta,
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
        $roleCodes = RoleMenu::alias('rm')
            ->join('sys_role r', 'rm.role_id = r.id')
            ->where('rm.menu_id', $menuId)
            ->where('r.is_deleted', 0)
            ->column('r.code');

        if (!empty($roleCodes)) {
            app()->make(RolePermService::class)->refreshRolePermsCacheBatch($roleCodes);
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

    /**
     *
     */
    public function addMenuForCodegen(int $parentMenuId, string $tableName, string $moduleName, string $businessName, string $entityName): void
    {
        $parent = Menu::find($parentMenuId);
        if (!$parent) {
            return;
        }

        //
        $sort = 1;
        $maxSortMenu = Menu::where('parent_id', $parentMenuId)->order('sort', 'desc')->find();
        if ($maxSortMenu) {
            $sort = (int) $maxSortMenu->sort + 1;
        }

        //
        $entityKebab = $this->toKebabCase($entityName);
        $treePath = $parent->tree_path . ',' . $parentMenuId;

        //
        $menuId = (int) Menu::insertGetId([
            'parent_id' => $parentMenuId,
            'type' => 'M',
            'name' => $businessName,
            'route_name' => $entityName,
            'route_path' => $entityKebab,
            'component' => $moduleName . '/' . $entityKebab . '/index',
            'sort' => $sort,
            'visible' => 1,
            'tree_path' => $treePath,
        ]);

        // 生成CURD按钮权限
        $permPrefix = $moduleName . ':' . str_replace('_', '-', $tableName) . ':';
        $actions = ['查询', '新增', '修改', '删除'];
        $perms = ['list', 'create', 'update', 'delete'];
        foreach ($actions as $i => $action) {
            Menu::insert([
                'parent_id' => $menuId,
                'type' => 'B',
                'name' => $action,
                'perm' => $permPrefix . $perms[$i],
                'sort' => $i + 1,
                'tree_path' => $treePath . ',' . $menuId,
            ]);
        }
    }

    /**
     *
     */
    private function toKebabCase(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $result = '';
        for ($i = 0; $i < strlen($s); $i++) {
            $c = $s[$i];
            if ($i > 0 && ctype_upper($c)) {
                $result .= '-';
            }
            $result .= strtolower($c);
        }
        return $result;
    }
}
