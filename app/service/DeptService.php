<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\Dept;

final class DeptService
{
    public function listDepts(?string $keywords = null, ?int $status = null, ?array $authUser = null): array
    {
        $query = Dept::where('is_deleted', 0)->order('sort', 'asc');

        // 数据权限过滤（支持多角色并集策略）
        if (is_array($authUser)) {
            $dataPermissionService = new DataPermissionService();
            $query = $dataPermissionService->apply($query, 'id', 'id', $authUser);
        }

        if ($keywords !== null && trim($keywords) !== '') {
            $kw = '%' . trim($keywords) . '%';
            $query = $query->whereLike('name', $kw);
        }

        if ($status !== null) {
            $query = $query->where('status', (int) $status);
        }

        $rows = $query->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row->toArray();
        }

        return $this->buildDeptTree(0, $list);
    }

    public function listDeptOptions(?array $authUser = null): array
    {
        $query = Dept::where('is_deleted', 0)
            ->order('sort', 'asc')
            ->field('id,name,parent_id,sort');

        // 数据权限过滤（支持多角色并集策略）
        if (is_array($authUser)) {
            $dataPermissionService = new DataPermissionService();
            $query = $dataPermissionService->apply($query, 'id', 'id', $authUser);
        }

        $rows = $query->select()->toArray();
        return $this->buildDeptOptionsTree(0, $rows);
    }

    public function getDeptForm(int $deptId): array
    {
        $dept = Dept::where('id', $deptId)->where('is_deleted', 0)->find();
        if ($dept === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '部门不存在');
        }

        $d = $dept->toArray();
        return [
            'id' => isset($d['id']) ? (string) $d['id'] : null,
            'name' => $d['name'] ?? null,
            'code' => $d['code'] ?? null,
            'parentId' => isset($d['parent_id']) ? (string) $d['parent_id'] : '0',
            'status' => isset($d['status']) ? (int) $d['status'] : null,
            'sort' => isset($d['sort']) ? (int) $d['sort'] : null,
        ];
    }

    public function saveDept(array $data): int
    {
        $id = isset($data['id']) && (string) $data['id'] !== '' ? (int) $data['id'] : null;
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $parentId = isset($data['parentId']) ? (int) $data['parentId'] : 0;

        if ($name === '' || $code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        if ($id !== null && $id > 0 && $parentId === $id) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '父部门不能为当前部门');
        }

        $existsCode = Dept::where('is_deleted', 0)
            ->where('code', $code)
            ->when($id !== null, function ($q) use ($id) {
                $q->where('id', '<>', $id);
            })
            ->count();

        if ($existsCode > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '部门编号已存在');
        }

        $treePath = $this->generateDeptTreePath($parentId);

        $entity = [
            'name' => $name,
            'code' => $code,
            'parent_id' => $parentId,
            'tree_path' => $treePath,
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 0,
            'status' => isset($data['status']) ? (int) $data['status'] : 1,
            'update_time' => date('Y-m-d H:i:s'),
        ];

        if ($id === null || $id <= 0) {
            $entity['create_time'] = date('Y-m-d H:i:s');
            $entity['is_deleted'] = 0;
            $dept = new Dept();
            $dept->save($entity);
            return (int) $dept->getAttr('id');
        }

        $dept = Dept::where('id', $id)->where('is_deleted', 0)->find();
        if ($dept === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '部门不存在');
        }

        $dept->save($entity);
        return $id;
    }

    public function deleteByIds(string $ids): bool
    {
        $ids = trim($ids);
        if ($ids === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $ids)), fn($v) => $v !== ''));
        $idList = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p)) {
                throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
            }
            $idList[] = (int) $p;
        }

        foreach ($idList as $id) {
            $childCount = Dept::where('is_deleted', 0)->where('parent_id', $id)->count();
            if ($childCount > 0) {
                throw new BusinessException(ResultCode::INVALID_USER_INPUT, '存在子部门，无法删除');
            }
        }

        Dept::whereIn('id', $idList)->update([
            'is_deleted' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function buildDeptTree(int $parentId, array $list): array
    {
        $tree = [];
        foreach ($list as $d) {
            if ((int) ($d['parent_id'] ?? 0) !== $parentId) {
                continue;
            }

            $node = [
                'id' => isset($d['id']) ? (string) $d['id'] : null,
                'parentId' => isset($d['parent_id']) ? (string) $d['parent_id'] : null,
                'name' => $d['name'] ?? null,
                'code' => $d['code'] ?? null,
                'sort' => isset($d['sort']) ? (int) $d['sort'] : null,
                'status' => isset($d['status']) ? (int) $d['status'] : null,
                'treePath' => $d['tree_path'] ?? null,
                'createTime' => $d['create_time'] ?? null,
                'updateTime' => $d['update_time'] ?? null,
            ];

            $children = $this->buildDeptTree((int) ($d['id'] ?? 0), $list);
            if (!empty($children)) {
                $node['children'] = $children;
            }

            $tree[] = $this->filterNulls($node);
        }

        return $tree;
    }

    private function generateDeptTreePath(int $parentId): string
    {
        if ($parentId <= 0) {
            return '0';
        }

        $parent = Dept::where('id', $parentId)->where('is_deleted', 0)->find();
        if ($parent === null) {
            return '0';
        }

        $p = $parent->toArray();
        $treePath = (string) ($p['tree_path'] ?? '0');
        if ($treePath === '') {
            $treePath = '0';
        }

        return $treePath . ',' . $parentId;
    }

    private function getDeptAndSubDeptIds(int $deptId): array
    {
        $ids = Dept::where('is_deleted', 0)
            ->where(function ($q) use ($deptId) {
                $q->where('id', $deptId)
                    ->whereOrRaw('FIND_IN_SET(?, tree_path)', [$deptId]);
            })
            ->column('id');

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
        return $ids;
    }

    private function buildDeptOptionsTree(int $parentId, array $rows): array
    {
        $tree = [];
        foreach ($rows as $r) {
            if ((int) ($r['parent_id'] ?? 0) !== $parentId) {
                continue;
            }

            $node = [
                'value' => (string) ($r['id'] ?? ''),
                'label' => (string) ($r['name'] ?? ''),
            ];

            $children = $this->buildDeptOptionsTree((int) ($r['id'] ?? 0), $rows);
            if (!empty($children)) {
                $node['children'] = $children;
            }

            $tree[] = $node;
        }

        return $tree;
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
