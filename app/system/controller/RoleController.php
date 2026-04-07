<?php declare(strict_types=1);

namespace app\system\controller;

use app\controller\BaseController;
use app\system\annotation\Log;
use app\system\enums\ActionType;
use app\system\service\RoleService;
use app\system\validate\RoleValidate;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="03.角色接口")
 */
final class RoleController extends BaseController
{
    /**
     * 分页查询角色列表
     *
     * @OA\Get(
     *     path="/api/v1/roles",
     *     summary="角色列表",
     *     tags={"03.角色接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function page(): \think\response\Json
    {
        [$list, $total] = $this->service(RoleService::class)->paginate(
            $this->getAllParams()
        );

        return $this->success($list, $total);
    }

    /**
     * 获取所有启用的角色（下拉框用）
     *
     * @OA\Get(
     *     path="/api/v1/roles/options",
     *     summary="角色下拉列表",
     *     tags={"03.角色接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function options(): \think\response\Json
    {
        $list = $this->service(RoleService::class)->getAllEnabled();

        return $this->success($list);
    }

    /**
     * 获取角色表单数据
     *
     * @OA\Get(
     *     path="/api/v1/roles/{id}/form",
     *     summary="获取角色表单数据",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function form(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(RoleService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '角色不存在');
        }

        return $this->success($data);
    }

    /**
     * 获取角色详情
     *
     * @OA\Get(
     *     path="/api/v1/roles/{id}",
     *     summary="获取角色详情",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function detail(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(RoleService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '角色不存在');
        }

        return $this->success($data);
    }

    /**
     * 创建角色
     *
     * @OA\Post(
     *     path="/api/v1/roles",
     *     summary="新增角色",
     *     tags={"03.角色接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::ROLE_CREATE)]
    public function create(): \think\response\Json
    {
        $data = $this->validate($this->getAllParams(), RoleValidate::class, 'create');

        $id = $this->service(RoleService::class)->create($data);

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新角色
     *
     * @OA\Put(
     *     path="/api/v1/roles/{id}",
     *     summary="修改角色",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::ROLE_UPDATE)]
    public function update(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->validate($this->getAllParams(), RoleValidate::class, 'update');

        $this->service(RoleService::class)->update($id, $data);

        return $this->success(null, '更新成功');
    }

    /**
     * 批量删除角色
     *
     * @OA\Delete(
     *     path="/api/v1/roles/{ids}",
     *     summary="删除角色",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="ids", in="path", description="角色ID，多个以英文逗号分割", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::ROLE_DELETE)]
    public function delete(): \think\response\Json
    {
        $ids = $this->getIdsParam();

        if (empty($ids)) {
            return $this->fail('A0410', '请选择要删除的角色');
        }

        $count = $this->service(RoleService::class)->deleteByIds($ids);

        return $this->success(['count' => $count], "成功删除 {$count} 个角色");
    }

    /**
     * 修改角色状态
     *
     * @OA\Patch(
     *     path="/api/v1/roles/{id}/status",
     *     summary="修改角色状态",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::ROLE_UPDATE)]
    public function status(): \think\response\Json
    {
        $id = $this->getIdParam();
        $status = (int) $this->getParam('status', 1);

        $this->service(RoleService::class)->updateStatus($id, $status);

        return $this->success(null, '状态修改成功');
    }

    /**
     * 获取角色的菜单ID集合
     *
     * @OA\Get(
     *     path="/api/v1/roles/{id}/menus",
     *     summary="获取角色菜单ID集合",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function menuIds(): \think\response\Json
    {
        $id = $this->getIdParam();
        $menuIds = $this->service(RoleService::class)->getMenuIds($id);

        return $this->success($menuIds);
    }

    /**
     * 角色分配菜单权限
     *
     * @OA\Put(
     *     path="/api/v1/roles/{id}/menus",
     *     summary="分配角色菜单权限",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::ROLE_UPDATE)]
    public function assignMenus(): \think\response\Json
    {
        $id = $this->getIdParam();
        $payload = $this->getJsonBody();

        if (!is_array($payload)) {
            return $this->fail('A0400', 'menuIds 参数格式错误');
        }

        $isSequential = array_is_list($payload);
        if (!$isSequential) {
            $keys = array_keys($payload);
            $isSequential = ($keys === []) || (
                array_reduce($keys, static fn ($carry, $k) => $carry && (is_int($k) || (is_string($k) && ctype_digit($k))), true)
                && array_map('intval', $keys) === range(0, count($keys) - 1)
            );
        }

        if (!$isSequential) {
            return $this->fail('A0400', 'menuIds 参数格式错误');
        }

        $menuIds = array_values(array_filter(array_map('intval', $payload), static fn ($v) => $v > 0));

        $this->service(RoleService::class)->syncMenus($id, $menuIds);

        return $this->success(null, '分配成功');
    }

    /**
     * 获取角色的部门ID集合(自定义数据权限)
     *
     * @OA\Get(
     *     path="/api/v1/roles/{id}/depts",
     *     summary="获取角色部门ID集合",
     *     tags={"03.角色接口"},
     *     @OA\Parameter(name="id", in="path", description="角色ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function deptIds(): \think\response\Json
    {
        $id = $this->getIdParam();
        $deptIds = $this->service(RoleService::class)->getDeptIds($id);

        return $this->success($deptIds);
    }
}
