<?php declare(strict_types=1);

namespace app\System\Controller;

use app\controller\ApiController;
use app\System\Service\RoleService;
use app\System\Validate\RoleValidate;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="03.角色管理")
 */
final class RoleController extends ApiController
{
    /**
     * 分页查询角色列表
     */
    public function page(): \think\response\Json
    {
        [$list, $total] = $this->service(RoleService::class)->paginate(
            $this->getAllParams()
        );

        $pagination = $this->getPaginationParams();

        return $this->successPaginate($list, $total, $pagination['page'], $pagination['pageSize']);
    }

    /**
     * 获取所有启用的角色（下拉框用）
     */
    public function options(): \think\response\Json
    {
        $list = $this->service(RoleService::class)->getAllEnabled();

        return $this->success($list);
    }

    /**
     * 获取角色表单数据
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
     */
    public function create(): \think\response\Json
    {
        $data = $this->validate($this->getAllParams(), RoleValidate::class, 'create');

        $id = $this->service(RoleService::class)->create($data);

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新角色
     */
    public function update(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->validate($this->getAllParams(), RoleValidate::class, 'update');

        $this->service(RoleService::class)->update($id, $data);

        return $this->success(null, '更新成功');
    }

    /**
     * 批量删除角色
     */
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
     */
    public function status(): \think\response\Json
    {
        $id = $this->getIdParam();
        $status = (int) $this->getParam('status', 1);

        $this->service(RoleService::class)->updateStatus($id, $status);

        return $this->success(null, '状态修改成功');
    }

    /**
     * 获取角色的菜单ID集合
     */
    public function menuIds(): \think\response\Json
    {
        $id = $this->getIdParam();
        $menuIds = $this->service(RoleService::class)->getMenuIds($id);

        return $this->success($menuIds);
    }

    /**
     * 角色分配菜单权限
     */
    public function assignMenus(): \think\response\Json
    {
        $id = $this->getIdParam();
        $menuIds = $this->request->post('menuIds', []);

        if (!is_array($menuIds)) {
            $menuIds = [];
        }

        $this->service(RoleService::class)->syncMenus($id, $menuIds);

        return $this->success(null, '分配成功');
    }

    /**
     * 获取角色的部门ID集合(自定义数据权限)
     */
    public function deptIds(): \think\response\Json
    {
        $id = $this->getIdParam();
        $deptIds = $this->service(RoleService::class)->getDeptIds($id);

        return $this->success($deptIds);
    }
}
