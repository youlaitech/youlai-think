<?php declare(strict_types=1);

namespace app\System\Controller;

use app\controller\ApiController;
use app\System\Service\MenuService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="04.菜单接口")
 */
final class MenuController extends ApiController
{
    /**
     * 获取菜单下拉数据
     */
    public function options(): \think\response\Json
    {
        $onlyParent = (bool) $this->getParam('onlyParent', false);
        $list = $this->service(MenuService::class)->getOptions($onlyParent);

        return $this->success($list);
    }

    /**
     * 获取当前用户路由菜单
     */
    public function routes(): \think\response\Json
    {
        $tree = $this->service(MenuService::class)->getUserMenuTree(
            $this->getAuthUserId(),
            $this->getAuthRoleCodes()
        );

        return $this->success($tree);
    }

    /**
     * 获取菜单树（管理用）
     */
    public function tree(): \think\response\Json
    {
        $tree = $this->service(MenuService::class)->getTree();

        return $this->success($tree);
    }

    /**
     * 获取所有菜单（平铺列表）
     */
    public function list(): \think\response\Json
    {
        $list = $this->service(MenuService::class)->getAll();

        return $this->success($list);
    }

    /**
     * 获取菜单表单数据
     */
    public function form(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(MenuService::class)->getFormById($id);

        if (!$data) {
            return $this->fail('A0400', '菜单不存在');
        }

        return $this->success($data);
    }

    /**
     * 获取菜单详情
     */
    public function detail(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(MenuService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '菜单不存在');
        }

        return $this->success($data);
    }

    /**
     * 创建菜单
     */
    public function create(): \think\response\Json
    {
        $id = $this->service(MenuService::class)->create($this->getAllParams());

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新菜单
     */
    public function update(): \think\response\Json
    {
        $id = $this->getIdParam();
        $this->service(MenuService::class)->update($id, $this->getAllParams());

        return $this->success(null, '更新成功');
    }

    /**
     * 删除菜单
     */
    public function delete(): \think\response\Json
    {
        $id = $this->getIdParam();
        $this->service(MenuService::class)->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 修改菜单显示状态
     */
    public function visible(): \think\response\Json
    {
        $id = $this->getIdParam();
        $visible = (int) $this->getParam('visible', 1);

        $this->service(MenuService::class)->updateVisible($id, $visible);

        return $this->success(null, '修改成功');
    }
}
