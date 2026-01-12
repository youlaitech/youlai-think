<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\RoleService;

final class RoleController extends ApiController
{
    public function page(): \think\Response
    {
        $pageNum = (int) $this->request->param('pageNum', 1);
        $pageSize = (int) $this->request->param('pageSize', 10);
        $keywords = (string) $this->request->param('keywords', '');

        [$list, $total] = (new RoleService())->getRolePage($pageNum, $pageSize, $keywords);
        return $this->okPage($list, $total);
    }

    public function options(): \think\Response
    {
        $list = (new RoleService())->listRoleOptions();
        return $this->ok($list);
    }

    public function form(int $roleId): \think\Response
    {
        $data = (new RoleService())->getRoleForm($roleId);
        return $this->ok($data);
    }

    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new RoleService())->saveRole($data, null);
        return $this->ok();
    }

    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new RoleService())->saveRole($data, $id);
        return $this->ok();
    }

    public function delete(string $ids): \think\Response
    {
        (new RoleService())->deleteRoles($ids);
        return $this->ok();
    }

    public function updateStatus(int $roleId): \think\Response
    {
        $status = $this->request->param('status');
        if ($status === null || $status === '') {
            $json = $this->getJsonBody();
            $status = is_array($json) ? ($json['status'] ?? null) : null;
        }

        if ($status === null || $status === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        (new RoleService())->updateRoleStatus($roleId, (int) $status);
        return $this->ok();
    }

    public function menuIds(int $roleId): \think\Response
    {
        $list = (new RoleService())->getRoleMenuIds($roleId);
        if (is_array($list)) {
            $list = array_values(array_map(static fn($v) => (string) $v, $list));
        }
        return $this->ok($list);
    }

    public function assignMenus(int $roleId): \think\Response
    {
        $json = $this->getJsonBody();
        if (!is_array($json)) {
            throw new BusinessException(ResultCode::USER_REQUEST_PARAMETER_ERROR);
        }

        (new RoleService())->assignMenusToRole($roleId, $json);
        return $this->ok();
    }
}
