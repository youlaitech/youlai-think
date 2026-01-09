<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\DeptService;

/**
 * 部门接口 /api/v1/depts
 *
 * 部门树 下拉选项 增删改查
 */
final class DeptController extends ApiController
{
    /**
     * 部门列表（树形）
     *
     * @return \think\Response
     */
    public function index(): \think\Response
    {
        $keywords = (string) $this->request->param('keywords', '');
        $status = $this->request->param('status');
        $status = $status === null || $status === '' ? null : (int) $status;

        $authUser = $this->getAuthUser();
        $list = (new DeptService())->listDepts($keywords, $status, $authUser);
        return $this->ok($list);
    }

    /**
     * 部门下拉选项
     *
     * @return \think\Response
     */
    public function options(): \think\Response
    {
        $authUser = $this->getAuthUser();
        $list = (new DeptService())->listDeptOptions($authUser);
        return $this->ok($list);
    }

    /**
     * 部门表单数据
     *
     * @param int $deptId 部门ID
     * @return \think\Response
     */
    public function form(int $deptId): \think\Response
    {
        $data = (new DeptService())->getDeptForm($deptId);
        return $this->ok($data);
    }

    /**
     * 新增部门
     *
     * @return \think\Response
     */
    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        $id = (new DeptService())->saveDept($data);
        return $this->ok($id);
    }

    /**
     * 修改部门
     *
     * @param int $deptId 部门ID
     * @return \think\Response
     */
    public function update(int $deptId): \think\Response
    {
        $data = $this->mergeJsonParams();
        $data['id'] = $deptId;
        $id = (new DeptService())->saveDept($data);
        return $this->ok($id);
    }

    /**
     * 删除部门（批量）
     *
     * @param string $ids 逗号分隔ID列表
     * @return \think\Response
     */
    public function delete(string $ids): \think\Response
    {
        (new DeptService())->deleteByIds($ids);
        return $this->ok();
    }
}
