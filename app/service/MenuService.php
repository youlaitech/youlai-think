<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\Menu;
use think\facade\Db;

final class MenuService
{
    public function listMenus(?string $keywords = null, ?int $status = null): array
    {
        $query = Menu::order('sort', 'asc');

        if ($keywords !== null && trim($keywords) !== '') {
            $kw = '%' . trim($keywords) . '%';
            $query = $query->whereLike('name', $kw);
        }

        if ($status !== null) {
            $query = $query->where('visible', (int) $status);
        }

        $rows = $query->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row->toArray();
        }

        return $this->buildMenuTree(0, $list);
    }

    public function listMenuOptions(bool $onlyParent): array
    {
        $query = Menu::order('sort', 'asc');
        if ($onlyParent) {
            $query = $query->whereIn('type', ['C', 'M']);
        }

        $rows = $query->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row->toArray();
        }

        return $this->buildMenuOptions(0, $list);
    }

    public function listCurrentUserRoutes(int $userId): array
    {
        $roles = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->column('r.code');

        $roles = array_values(array_unique(array_filter($roles, fn($v) => $v !== null && $v !== '')));

        if (empty($roles)) {
            return [];
        }

        $menuRows = [];
        if (in_array('ROOT', $roles, true)) {
            $rows = Menu::where('type', '<>', 'B')->order('sort', 'asc')->select();
            foreach ($rows as $row) {
                $menuRows[] = $row->toArray();
            }
        } else {
            $rows = Db::name('sys_role_menu')
                ->alias('rm')
                ->join('sys_role r', 'rm.role_id = r.id')
                ->join('sys_menu m', 'rm.menu_id = m.id')
                ->whereIn('r.code', $roles)
                ->where('m.type', '<>', 'B')
                ->order('m.sort', 'asc')
                ->field('m.*')
                ->select()
                ->toArray();

            $seen = [];
            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $menuRows[] = $r;
            }
        }

        return $this->buildRoutes(0, $menuRows);
    }

    public function getMenuForm(int $id): array
    {
        $menu = Menu::where('id', $id)->find();
        if ($menu === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '菜单不存在');
        }

        $m = $menu->toArray();
        $params = [];
        $paramsJson = (string) ($m['params'] ?? '');
        if ($paramsJson !== '') {
            $decoded = json_decode($paramsJson, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    $params[] = ['key' => (string) $k, 'value' => (string) $v];
                }
            }
        }

        return [
            'id' => (string) ($m['id'] ?? ''),
            'parentId' => (string) ($m['parent_id'] ?? '0'),
            'name' => (string) ($m['name'] ?? ''),
            'type' => (string) ($m['type'] ?? ''),
            'routeName' => $m['route_name'] ?? null,
            'routePath' => $m['route_path'] ?? null,
            'redirect' => $m['redirect'] ?? null,
            'component' => $m['component'] ?? null,
            'icon' => $m['icon'] ?? null,
            'sort' => isset($m['sort']) ? (int) $m['sort'] : null,
            'visible' => isset($m['visible']) ? (int) $m['visible'] : null,
            'perm' => $m['perm'] ?? null,
            'alwaysShow' => isset($m['always_show']) ? (int) $m['always_show'] : null,
            'keepAlive' => isset($m['keep_alive']) ? (int) $m['keep_alive'] : null,
            'params' => $params,
        ];
    }

    public function saveMenu(array $data): bool
    {
        $id = isset($data['id']) && (string) $data['id'] !== '' ? (int) $data['id'] : null;
        $parentId = isset($data['parentId']) ? (int) $data['parentId'] : 0;

        if ($id !== null && $id > 0 && $parentId === $id) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '父级菜单不能为当前菜单');
        }

        $type = strtoupper((string) ($data['type'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $routePath = trim((string) ($data['routePath'] ?? ''));

        if ($type === '' || $name === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $isExternalLink = $type === 'M' && preg_match('/^https?:\/\//i', $routePath) === 1;

        $routeName = trim((string) ($data['routeName'] ?? ''));
        if ($type === 'M' && !$isExternalLink) {
            if ($routeName === '') {
                throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
            }

            $existsQuery = Menu::where('route_name', $routeName);
            if ($id !== null && $id > 0) {
                $existsQuery = $existsQuery->where('id', '<>', $id);
            }

            if ($existsQuery->count() > 0) {
                throw new BusinessException(ResultCode::INVALID_USER_INPUT, '路由名称已存在');
            }
        }

        if ($type === 'C') {
            if ($parentId === 0 && $routePath !== '' && !str_starts_with($routePath, '/')) {
                $routePath = '/' . $routePath;
            }
        }

        $component = $data['component'] ?? null;
        if ($type === 'C') {
            $component = 'Layout';
        } elseif ($isExternalLink) {
            $component = null;
        }

        $paramsMap = null;
        $params = $data['params'] ?? null;
        if (is_array($params) && !empty($params)) {
            $m = [];
            foreach ($params as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $k = trim((string) ($p['key'] ?? ''));
                $v = (string) ($p['value'] ?? '');
                if ($k === '') {
                    continue;
                }
                $m[$k] = $v;
            }
            if (!empty($m)) {
                $paramsMap = json_encode($m, JSON_UNESCAPED_UNICODE);
            }
        }

        $entity = [
            'parent_id' => $parentId,
            'name' => $name,
            'type' => $type,
            'route_name' => ($type === 'M' && !$isExternalLink) ? $routeName : null,
            'route_path' => $routePath,
            'component' => $component,
            'perm' => $data['perm'] ?? null,
            'visible' => isset($data['visible']) ? (int) $data['visible'] : 1,
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 0,
            'icon' => $data['icon'] ?? null,
            'redirect' => $data['redirect'] ?? null,
            'keep_alive' => isset($data['keepAlive']) ? (int) $data['keepAlive'] : 0,
            'always_show' => isset($data['alwaysShow']) ? (int) $data['alwaysShow'] : 0,
            'params' => $paramsMap,
            'update_time' => date('Y-m-d H:i:s'),
        ];

        if ($id === null || $id <= 0) {
            $entity['create_time'] = date('Y-m-d H:i:s');
        }

        $treePath = $this->generateMenuTreePath($parentId);
        $entity['tree_path'] = $treePath;

        if ($id === null || $id <= 0) {
            $menu = new Menu();
            $menu->save($entity);
            return true;
        }

        $menu = Menu::where('id', $id)->find();
        if ($menu === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '菜单不存在');
        }

        $menu->save($entity);
        return true;
    }

    public function deleteMenu(int $id): bool
    {
        if ($id <= 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '菜单不存在');
        }

        $childCount = Menu::where('parent_id', $id)->count();
        if ($childCount > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '存在子菜单，无法删除');
        }

        $menu = Menu::where('id', $id)->find();
        if ($menu === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '菜单不存在');
        }

        $menu->delete();
        return true;
    }

    public function updateMenuVisible(int $menuId, int $visible): bool
    {
        $menu = Menu::where('id', $menuId)->find();
        if ($menu === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '菜单不存在');
        }

        $menu->save([
            'visible' => $visible,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function buildMenuTree(int $parentId, array $menus): array
    {
        $tree = [];
        foreach ($menus as $m) {
            if ((int) ($m['parent_id'] ?? 0) !== $parentId) {
                continue;
            }

            $node = [
                'id' => isset($m['id']) ? (string) $m['id'] : null,
                'parentId' => isset($m['parent_id']) ? (string) $m['parent_id'] : null,
                'name' => $m['name'] ?? null,
                'type' => $m['type'] ?? null,
                'routeName' => $m['route_name'] ?? null,
                'routePath' => $m['route_path'] ?? null,
                'component' => $m['component'] ?? null,
                'sort' => isset($m['sort']) ? (int) $m['sort'] : null,
                'visible' => isset($m['visible']) ? (int) $m['visible'] : null,
                'icon' => $m['icon'] ?? null,
                'redirect' => $m['redirect'] ?? null,
                'perm' => $m['perm'] ?? null,
            ];

            $children = $this->buildMenuTree((int) ($m['id'] ?? 0), $menus);
            if (!empty($children)) {
                $node['children'] = $children;
            }

            $tree[] = $this->filterNulls($node);
        }

        return $tree;
    }

    private function buildMenuOptions(int $parentId, array $menus): array
    {
        $tree = [];
        foreach ($menus as $m) {
            if ((int) ($m['parent_id'] ?? 0) !== $parentId) {
                continue;
            }

            $node = [
                'value' => isset($m['id']) ? (string) $m['id'] : '0',
                'label' => (string) ($m['name'] ?? ''),
            ];

            $children = $this->buildMenuOptions((int) ($m['id'] ?? 0), $menus);
            if (!empty($children)) {
                $node['children'] = $children;
            }

            $tree[] = $node;
        }

        return $tree;
    }

    private function buildRoutes(int $parentId, array $menus): array
    {
        $routes = [];
        foreach ($menus as $m) {
            if ((int) ($m['parent_id'] ?? 0) !== $parentId) {
                continue;
            }

            $route = $this->toRouteVo($m);

            $children = $this->buildRoutes((int) ($m['id'] ?? 0), $menus);
            if (!empty($children)) {
                $route['children'] = $children;
            }

            $routes[] = $this->filterNulls($route);
        }

        return $routes;
    }

    private function toRouteVo(array $menu): array
    {
        $id = (int) ($menu['id'] ?? 0);
        $routePath = (string) ($menu['route_path'] ?? '');
        $externalLink = preg_match('/^https?:\/\//i', $routePath) === 1;

        $routeName = (string) ($menu['route_name'] ?? '');
        if ($routeName === '') {
            $routeName = $externalLink ? ('ext-' . $id) : $this->guessRouteNameFromPath($routePath);
        }

        $meta = [
            'title' => $menu['name'] ?? null,
            'icon' => $menu['icon'] ?? null,
            'hidden' => ((int) ($menu['visible'] ?? 1)) === 0,
            'alwaysShow' => ((int) ($menu['always_show'] ?? 0)) === 1,
        ];

        if ((string) ($menu['type'] ?? '') === 'M' && ((int) ($menu['keep_alive'] ?? 0)) === 1) {
            $meta['keepAlive'] = true;
        }

        $paramsJson = (string) ($menu['params'] ?? '');
        if ($paramsJson !== '') {
            $decoded = json_decode($paramsJson, true);
            if (is_array($decoded) && !empty($decoded)) {
                $meta['params'] = $decoded;
            }
        }

        return [
            'path' => $routePath,
            'component' => $externalLink ? null : ($menu['component'] ?? null),
            'redirect' => $menu['redirect'] ?? null,
            'name' => $routeName,
            'meta' => $this->filterNulls($meta),
        ];
    }

    private function guessRouteNameFromPath(string $routePath): string
    {
        $p = trim($routePath);
        if ($p === '') {
            return '';
        }

        $p = trim($p, '/');
        $parts = explode('/', $p);
        $last = $parts[count($parts) - 1] ?? '';
        if ($last === '') {
            $last = $p;
        }

        $last = str_replace(['-', '_'], ' ', $last);
        $last = ucwords($last);
        $last = str_replace(' ', '', $last);

        return $last;
    }

    private function generateMenuTreePath(int $parentId): string
    {
        if ($parentId <= 0) {
            return '0';
        }

        $parent = Menu::where('id', $parentId)->find();
        if ($parent === null) {
            return '0';
        }

        $p = $parent->toArray();
        $parentTree = (string) ($p['tree_path'] ?? '0');
        if ($parentTree === '') {
            $parentTree = '0';
        }

        return $parentTree . ',' . $parentId;
    }

    private function filterNulls(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $v = $this->filterNulls($v);
                if ($v === []) {
                    unset($data[$k]);
                    continue;
                }
                $data[$k] = $v;
                continue;
            }

            if ($v === null) {
                unset($data[$k]);
                continue;
            }
        }

        return $data;
    }
}
