<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\Role;
use think\facade\Db;

final class RoleService
{
    public function getRolePage(int $pageNum, int $pageSize, ?string $keywords = null): array
    {
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $query = Role::where('is_deleted', 0);

        if ($keywords !== null && trim($keywords) !== '') {
            $kw = '%' . trim($keywords) . '%';
            $query = $query->where(function ($q) use ($kw) {
                $q->whereLike('name', $kw)->whereOrLike('code', $kw);
            });
        }

        $total = (int) $query->count();

        $rows = $query
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->page($pageNum, $pageSize)
            ->field('id,name,code,status,sort,create_time,update_time')
            ->select();

        $list = [];
        foreach ($rows as $row) {
            $r = $row->toArray();
            $list[] = [
                'id' => isset($r['id']) ? (string) $r['id'] : null,
                'name' => $r['name'] ?? null,
                'code' => $r['code'] ?? null,
                'status' => isset($r['status']) ? (int) $r['status'] : null,
                'sort' => isset($r['sort']) ? (int) $r['sort'] : null,
                'createTime' => $r['create_time'] ?? null,
                'updateTime' => $r['update_time'] ?? null,
            ];
        }

        return [$list, $total];
    }

    public function listRoleOptions(): array
    {
        $rows = Db::name('sys_role')
            ->where('is_deleted', 0)
            ->order('sort', 'asc')
            ->field('id,name')
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'label' => (string) ($r['name'] ?? ''),
                'value' => (string) ($r['id'] ?? ''),
            ];
        }
        return $list;
    }

    public function getRoleForm(int $roleId): array
    {
        $role = Role::where('id', $roleId)->where('is_deleted', 0)->find();
        if ($role === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '角色不存在');
        }

        $r = $role->toArray();

        return [
            'id' => isset($r['id']) ? (string) $r['id'] : null,
            'name' => $r['name'] ?? null,
            'code' => $r['code'] ?? null,
            'sort' => isset($r['sort']) ? (int) $r['sort'] : null,
            'status' => isset($r['status']) ? (int) $r['status'] : null,
            'dataScope' => isset($r['data_scope']) ? (int) $r['data_scope'] : null,
            'remark' => null,
        ];
    }

    public function saveRole(array $data, ?int $roleId = null): bool
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));

        if ($name === '' || $code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $existsName = Role::where('is_deleted', 0)
            ->where('name', $name)
            ->when($roleId !== null, function ($q) use ($roleId) {
                $q->where('id', '<>', $roleId);
            })
            ->count();

        if ($existsName > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '角色名称已存在');
        }

        $existsCode = Role::where('is_deleted', 0)
            ->where('code', $code)
            ->when($roleId !== null, function ($q) use ($roleId) {
                $q->where('id', '<>', $roleId);
            })
            ->count();

        if ($existsCode > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '角色编码已存在');
        }

        $entity = [
            'name' => $name,
            'code' => $code,
            'sort' => isset($data['sort']) ? (int) $data['sort'] : 0,
            'status' => isset($data['status']) ? (int) $data['status'] : 1,
            'data_scope' => isset($data['dataScope']) ? (int) $data['dataScope'] : null,
            'update_time' => date('Y-m-d H:i:s'),
        ];

        if ($roleId === null || $roleId <= 0) {
            $entity['create_time'] = date('Y-m-d H:i:s');
            $entity['is_deleted'] = 0;
            $role = new Role();
            $role->save($entity);
            return true;
        }

        $role = Role::where('id', $roleId)->where('is_deleted', 0)->find();
        if ($role === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '角色不存在');
        }

        $role->save($entity);
        return true;
    }

    public function deleteRoles(string $ids): bool
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

        if (empty($idList)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT);
        }

        Role::whereIn('id', $idList)->update([
            'is_deleted' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function getRoleMenuIds(int $roleId): array
    {
        return array_map('intval', Db::name('sys_role_menu')->where('role_id', $roleId)->column('menu_id'));
    }

    public function assignMenusToRole(int $roleId, array $menuIds): void
    {
        $role = Role::where('id', $roleId)->where('is_deleted', 0)->find();
        if ($role === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '角色不存在');
        }

        $ids = [];
        foreach ($menuIds as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[] = (int) $id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

        Db::transaction(function () use ($roleId, $ids) {
            Db::name('sys_role_menu')->where('role_id', $roleId)->delete();

            if (empty($ids)) {
                return;
            }

            $rows = [];
            foreach ($ids as $menuId) {
                $rows[] = ['role_id' => $roleId, 'menu_id' => $menuId];
            }

            Db::name('sys_role_menu')->insertAll($rows);
        });
    }
}
