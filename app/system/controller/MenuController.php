<?php declare(strict_types=1);

namespace app\system\controller;

use app\controller\BaseController;
use app\system\annotation\Log;
use app\system\enums\ActionType;
use app\system\service\MenuService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="04.菜单接口")
 */
final class MenuController extends BaseController
{
    /**
     * 获取菜单下拉数据
     *
     * @OA\Get(
     *     path="/api/v1/menus/options",
     *     summary="菜单下拉列表",
     *     tags={"04.菜单接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function options(): \think\response\Json
    {
        $onlyParent = (bool) $this->getParam('only_parent', false);
        $list = $this->service(MenuService::class)->getOptions($onlyParent);

        return $this->success($list);
    }

    /**
     * 获取当前用户路由菜单
     *
     * @OA\Get(
     *     path="/api/v1/menus/routes",
     *     summary="获取当前用户路由菜单",
     *     tags={"04.菜单接口"},
     *     @OA\Response(response="200", description="成功")
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/menus/tree",
     *     summary="菜单树",
     *     tags={"04.菜单接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function tree(): \think\response\Json
    {
        $tree = $this->service(MenuService::class)->getTree();

        return $this->success($tree);
    }

    /**
     * 获取所有菜单（平铺列表）
     *
     * @OA\Get(
     *     path="/api/v1/menus",
     *     summary="菜单列表",
     *     tags={"04.菜单接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function list(): \think\response\Json
    {
        $list = $this->service(MenuService::class)->getAll();

        return $this->success($list);
    }

    /**
     * 获取菜单表单数据
     *
     * @OA\Get(
     *     path="/api/v1/menus/{id}/form",
     *     summary="获取菜单表单数据",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/menus/{id}",
     *     summary="获取菜单详情",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
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
     *
     * @OA\Post(
     *     path="/api/v1/menus",
     *     summary="新增菜单",
     *     tags={"04.菜单接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::MENU_CREATE)]
    public function create(): \think\response\Json
    {
        $id = $this->service(MenuService::class)->create($this->getAllParams());

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新菜单
     *
     * @OA\Put(
     *     path="/api/v1/menus/{id}",
     *     summary="修改菜单",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::MENU_UPDATE)]
    public function update(): \think\response\Json
    {
        $id = $this->getIdParam();
        $this->service(MenuService::class)->update($id, $this->getAllParams());

        return $this->success(null, '更新成功');
    }

    /**
     * 删除菜单
     *
     * @OA\Delete(
     *     path="/api/v1/menus/{id}",
     *     summary="删除菜单",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::MENU_DELETE)]
    public function delete(): \think\response\Json
    {
        $id = $this->getIdParam();
        $this->service(MenuService::class)->delete($id);

        return $this->success(null, '删除成功');
    }

    /**
     * 修改菜单显示状态
     *
     * @OA\Patch(
     *     path="/api/v1/menus/{id}/visible",
     *     summary="修改菜单显示状态",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::MENU_UPDATE)]
    public function visible(): \think\response\Json
    {
        $id = $this->getIdParam();
        $visible = (int) $this->getParam('visible', 1);

        $this->service(MenuService::class)->updateVisible($id, $visible);

        return $this->success(null, '修改成功');
    }
}
