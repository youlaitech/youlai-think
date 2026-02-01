<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\RoleService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="03.角色接口")
 */
final class RoleController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/roles",
     *     summary="角色分页列表",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Parameter(name="keywords", in="query", description="关键字", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function page(): \think\Response
    {
        $pageNum = (int) $this->request->param('pageNum', 1);
        $pageSize = (int) $this->request->param('pageSize', 10);
        $keywords = (string) $this->request->param('keywords', '');

        [$list, $total] = (new RoleService())->getRolePage($pageNum, $pageSize, $keywords);
        return $this->okPage($list, $total);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/options",
     *     summary="角色下拉列表",
     *     tags={"03.角色接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function options(): \think\Response
    {
        $list = (new RoleService())->listRoleOptions();
        return $this->ok($list);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{roleId}/form",
     *     summary="获取角色表单数据",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="roleId", in="path", description="角色ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function form(int $roleId): \think\Response
    {
        $data = (new RoleService())->getRoleForm($roleId);
        return $this->ok($data);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles",
     *     summary="新增角色",
     *     tags={"03.角色接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new RoleService())->saveRole($data, null);
        return $this->ok();
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{id}",
     *     summary="修改角色",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new RoleService())->saveRole($data, $id);
        return $this->ok();
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/roles/{ids}",
     *     summary="删除角色",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="ids", in="path", description="删除角色，多个以英文逗号(,)拼接", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function delete(string $ids): \think\Response
    {
        (new RoleService())->deleteRoles($ids);
        return $this->ok();
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{roleId}/status",
     *     summary="修改角色状态",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="roleId", in="path", description="角色ID", required=true),
     *     @OA\Parameter(name="status", in="query", description="状态(1:启用;0:禁用)", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
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

        // 状态统一转为整型，避免字符串状态校验失败
        (new RoleService())->updateRoleStatus($roleId, (int) $status);
        return $this->ok();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{roleId}/menuIds",
     *     summary="获取角色的菜单ID集合",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="roleId", in="path", description="角色ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function menuIds(int $roleId): \think\Response
    {
        $list = (new RoleService())->getRoleMenuIds($roleId);
        if (is_array($list)) {
            $list = array_values(array_map(static fn($v) => (string) $v, $list));
        }
        return $this->ok($list);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{roleId}/menus",
     *     summary="角色分配菜单权限",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="roleId", in="path", description="角色ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="array", @OA\Items(type="integer"))),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function assignMenus(int $roleId): \think\Response
    {
        $json = $this->getJsonBody();
        if (!is_array($json)) {
            throw new BusinessException(ResultCode::USER_REQUEST_PARAMETER_ERROR);
        }

        // menuIds 为空时表示清空角色菜单权限
        (new RoleService())->assignMenusToRole($roleId, $json);
        return $this->ok();
    }
}
