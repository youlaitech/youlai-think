<?php declare(strict_types=1);

namespace app\system\service;

use app\common\exception\BusinessException;
use app\system\model\Dept;
use think\facade\Db;

final class DeptService
{
    /**
     * 获取部门树
     */
    public function getTree(): array
    {
        $list = Dept::where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->buildTree($list);
    }

    /**
     * 获取部门列表（树形结构，管理页面用）
     */
    public function getAll(): array
    {
        $list = Dept::order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return $this->buildTree($list);
    }

    /**
     * 获取部门下拉选项
     */
    public function getOptions(): array
    {
        $list = Dept::where('status', 1)
            ->field(['id', 'name', 'parent_id'])
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        return $this->buildOptions($list, 0);
    }

    /**
     * 根据ID获取部门详情
     */
    public function getById(int $id): ?array
    {
        return Dept::find($id)?->toArray();
    }

    /**
     * 创建部门
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        // 计算树路径
        $treePath = $this->buildTreePath((int) ($data['parent_id'] ?? 0));

        return (int) Dept::insertGetId([
            'parent_id' => $data['parent_id'] ?? 0,
            'name' => $data['name'],
            'code' => $data['code'] ?? '',
            'sort' => $data['sort'] ?? 0,
            'status' => $data['status'] ?? 1,
            'tree_path' => $treePath,
            'create_time' => $now,
            'update_time' => $now,
        ]);
    }

    /**
     * 更新部门
     */
    public function update(int $id, array $data): bool
    {
        $dept = Dept::find($id);
        if (!$dept) {
            throw new BusinessException('部门不存在');
        }

        // 不能把自己设为父级
        if (isset($data['parent_id']) && (int) $data['parent_id'] === $id) {
            throw new BusinessException('父级部门不能是自己');
        }

        // 更新树路径
        if (isset($data['parent_id']) && (int) $data['parent_id'] !== (int) $dept->parent_id) {
            $dept->tree_path = $this->buildTreePath((int) $data['parent_id']);
            // 更新子部门的树路径
            $this->updateChildrenTreePath($id, $dept->tree_path . ',' . $id);
        }

        $dept->parent_id = $data['parent_id'] ?? $dept->parent_id;
        $dept->name = $data['name'] ?? $dept->name;
        $dept->code = $data['code'] ?? $dept->code;
        $dept->sort = $data['sort'] ?? $dept->sort;
        $dept->status = $data['status'] ?? $dept->status;

        return $dept->save();
    }

    /**
     * 删除部门
     */
    public function delete(int $id): bool
    {
        // 检查是否有子部门
        $childCount = Dept::where('parent_id', $id)->count();
        if ($childCount > 0) {
            throw new BusinessException('存在子部门，无法删除');
        }

        // 检查是否有用户
        $userCount = Db::name('sys_user')->where('dept_id', $id)->where('is_deleted', 0)->count();
        if ($userCount > 0) {
            throw new BusinessException('部门下存在用户，无法删除');
        }

        return Db::name('sys_dept')->where('id', $id)->update([
            'is_deleted' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    /**
     * 获取部门及所有子部门ID
     */
    public function getDescendantIds(int $deptId): array
    {
        $dept = Dept::find($deptId);
        if (!$dept) {
            return [];
        }

        return $dept->getDescendantIds();
    }

    /**
     * 构建下拉选项树
     */
    private function buildOptions(array $list, int $parentId): array
    {
        $options = [];

        foreach ($list as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $option = [
                    'value' => (string) $item['id'],
                    'label' => $item['name'],
                ];
                $children = $this->buildOptions($list, (int) $item['id']);
                if ($children) {
                    $option['children'] = $children;
                }
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * 构建部门树结构
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
     * 生成部门 tree_path（如 0,5,12）
     */
    private function buildTreePath(int $parentId): string
    {
        if ($parentId <= 0) {
            return '0';
        }

        $parent = Dept::find($parentId);
        if (!$parent) {
            return '0';
        }

        return $parent->tree_path ? $parent->tree_path . ',' . $parentId : (string) $parentId;
    }

    private function updateChildrenTreePath(int $parentId, string $parentPath): void
    {
        $children = Dept::where('parent_id', $parentId)->select();

        foreach ($children as $child) {
            $child->tree_path = $parentPath;
            $child->save();

            // 递归更新子部门
            $this->updateChildrenTreePath((int) $child->id, $parentPath . ',' . $child->id);
        }
    }
}
