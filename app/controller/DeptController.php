<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\DeptService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="05.部门接口")
 */
final class DeptController extends ApiController
{
    /**
     * 部门列表（树形）
     *
     * @OA\Get(
     *     path="/api/v1/depts",
     *     summary="部门列表",
     *     tags={"05.部门接口"},
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

        $authUser = $this->getAuthUser();
        $list = (new DeptService())->listDepts($keywords, $status, $authUser);
        return $this->ok($list);
    }

    /**
     * 部门下拉选项
     *
     * @OA\Get(
     *     path="/api/v1/depts/options",
     *     summary="部门下拉列表",
     *     tags={"05.部门接口"},
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Get(
     *     path="/api/v1/depts/{deptId}/form",
     *     summary="获取部门表单数据",
     *     tags={"05.部门接口"},
     *     @OA\Parameter(name="deptId", in="path", description="部门ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Post(
     *     path="/api/v1/depts",
     *     summary="新增部门",
     *     tags={"05.部门接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Put(
     *     path="/api/v1/depts/{deptId}",
     *     summary="修改部门",
     *     tags={"05.部门接口"},
     *     @OA\Parameter(name="deptId", in="path", description="部门ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Delete(
     *     path="/api/v1/depts/{ids}",
     *     summary="删除部门",
     *     tags={"05.部门接口"},
     *     @OA\Parameter(name="ids", in="path", description="部门ID，多个以英文逗号(,)分割", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
