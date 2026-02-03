<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\MenuService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="04.菜单接口")
 */
final class MenuController extends ApiController
{
    /**
     * 菜单列表（树形）
     *
     * @OA\Get(
     *     path="/api/v1/menus",
     *     summary="菜单列表",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="keywords", in="query", description="关键字", required=false),
     *     @OA\Parameter(name="status", in="query", description="状态", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function index(): \think\Response
    {
        $keywords = (string) $this->request->param('keywords', '');
        $status = $this->request->param('status');
        $status = $status === null || $status === '' ? null : (int) $status;

        $list = (new MenuService())->listMenus($keywords, $status);
        return $this->ok($list);
    }

    /**
     * 菜单下拉选项
     *
     * @OA\Get(
     *     path="/api/v1/menus/options",
     *     summary="菜单下拉列表",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="onlyParent", in="query", description="是否只查询父级菜单", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function options(): \think\Response
    {
        $onlyParent = (string) $this->request->param('onlyParent', 'false');
        // 字符串转布尔，兼容多种前端传参格式
        $onlyParentBool = in_array(strtolower($onlyParent), ['1', 'true', 'yes', 'on'], true);

        $list = (new MenuService())->listMenuOptions($onlyParentBool);
        return $this->ok($list);
    }

    /**
     * 当前用户动态路由
     *
     * @OA\Get(
     *     path="/api/v1/menus/routes",
     *     summary="当前用户菜单路由列表",
     *     tags={"04.菜单接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function routes(): \think\Response
    {
        $userId = $this->getAuthUserId();

        // 根据当前用户角色动态返回路由树
        $list = (new MenuService())->listCurrentUserRoutes($userId);
        return $this->ok($list);
    }

    /**
     * 菜单表单数据
     *
     * @OA\Get(
     *     path="/api/v1/menus/{id}/form",
     *     summary="菜单表单数据",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 菜单ID
     * @return \think\Response
     */
    public function form(int $id): \think\Response
    {
        $data = (new MenuService())->getMenuForm($id);
        return $this->ok($data);
    }

    /**
     * 新增菜单
     *
     * @OA\Post(
     *     path="/api/v1/menus",
     *     summary="新增菜单",
     *     tags={"04.菜单接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new MenuService())->saveMenu($data);
        return $this->ok();
    }

    /**
     * 修改菜单
     *
     * @OA\Put(
     *     path="/api/v1/menus/{id}",
     *     summary="修改菜单",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 菜单ID
     * @return \think\Response
     */
    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        $data['id'] = $id;
        (new MenuService())->saveMenu($data);
        return $this->ok();
    }

    /**
     * 删除菜单
     *
     * @OA\Delete(
     *     path="/api/v1/menus/{id}",
     *     summary="删除菜单",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="id", in="path", description="菜单ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 菜单ID
     * @return \think\Response
     */
    public function delete(int $id): \think\Response
    {
        (new MenuService())->deleteMenu($id);
        return $this->ok();
    }

    /**
     * 修改菜单显示状态
     *
     * @OA\Patch(
     *     path="/api/v1/menus/{menuId}",
     *     summary="修改菜单显示状态",
     *     tags={"04.菜单接口"},
     *     @OA\Parameter(name="menuId", in="path", description="菜单ID", required=true),
     *     @OA\Parameter(name="visible", in="query", description="显示状态(1:显示;0:隐藏)", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $menuId 菜单ID
     * @return \think\Response
     * @throws BusinessException 参数缺失时抛出
     */
    public function updateVisible(int $menuId): \think\Response
    {
        $visible = $this->request->param('visible');
        if ($visible === null || $visible === '') {
            $json = $this->getJsonBody();
            $visible = $json['visible'] ?? null;
        }

        if ($visible === null || $visible === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        (new MenuService())->updateMenuVisible($menuId, (int) $visible);
        return $this->ok();
    }
}
