<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\MenuService;

/**
 * 菜单接口 /api/v1/menus
 *
 * 菜单树 下拉选项 动态路由
 */
final class MenuController extends ApiController
{
    /**
     * 菜单列表（树形）
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
     * @return \think\Response
     */
    public function options(): \think\Response
    {
        $onlyParent = (string) $this->request->param('onlyParent', 'false');
        $onlyParentBool = in_array(strtolower($onlyParent), ['1', 'true', 'yes', 'on'], true);

        $list = (new MenuService())->listMenuOptions($onlyParentBool);
        return $this->ok($list);
    }

    /**
     * 当前用户动态路由
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function routes(): \think\Response
    {
        $userId = $this->getAuthUserId();

        $list = (new MenuService())->listCurrentUserRoutes($userId);
        return $this->ok($list);
    }

    /**
     * 菜单表单数据
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
