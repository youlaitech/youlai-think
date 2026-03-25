<?php declare(strict_types=1);

namespace app\system\controller;

use app\common\controller\BaseController;
use app\system\annotation\Log;
use app\system\enums\ActionType;
use app\system\service\DeptService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="05.部门管理")
 */
final class DeptController extends BaseController
{
    /**
     * 获取部门下拉选项
     *
     * @OA\Get(
     *     path="/api/v1/depts/options",
     *     summary="部门下拉列表",
     *     tags={"05.部门管理"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function options(): \think\response\Json
    {
        $list = $this->service(DeptService::class)->getOptions();

        return $this->success($list);
    }

    /**
     * 获取部门树
     *
     * @OA\Get(
     *     path="/api/v1/depts/tree",
     *     summary="部门树",
     *     tags={"05.部门管理"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function tree(): \think\response\Json
    {
        $tree = $this->service(DeptService::class)->getTree();

        return $this->success($tree);
    }

    /**
     * 获取所有部门（平铺列表）
     *
     * @OA\Get(
     *     path="/api/v1/depts",
     *     summary="部门列表",
     *     tags={"05.部门管理"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function list(): \think\response\Json
    {
        $list = $this->service(DeptService::class)->getAll();

        return $this->success($list);
    }

    /**
     * 获取部门详情
     *
     * @OA\Get(
     *     path="/api/v1/depts/{id}",
     *     summary="获取部门详情",
     *     tags={"05.部门管理"},
     *     @OA\Parameter(name="id", in="path", description="部门ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function detail(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(DeptService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '部门不存在');
        }

        return $this->success($data);
    }

    /**
     * 获取部门表单数据
     *
     * @OA\Get(
     *     path="/api/v1/depts/{id}/form",
     *     summary="获取部门表单数据",
     *     tags={"05.部门管理"},
     *     @OA\Parameter(name="id", in="path", description="部门ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function form(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(DeptService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '部门不存在');
        }

        return $this->success($data);
    }

    /**
     * 创建部门
     *
     * @OA\Post(
     *     path="/api/v1/depts",
     *     summary="新增部门",
     *     tags={"05.部门管理"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::DEPT_CREATE)]
    public function create(): \think\response\Json
    {
        $id = $this->service(DeptService::class)->create($this->getAllParams());

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新部门
     *
     * @OA\Put(
     *     path="/api/v1/depts/{id}",
     *     summary="修改部门",
     *     tags={"05.部门管理"},
     *     @OA\Parameter(name="id", in="path", description="部门ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::DEPT_UPDATE)]
    public function update(): \think\response\Json
    {
        $id = $this->getIdParam();
        $this->service(DeptService::class)->update($id, $this->getAllParams());

        return $this->success(null, '更新成功');
    }

    /**
     * 删除部门
     *
     * @OA\Delete(
     *     path="/api/v1/depts/{id}",
     *     summary="删除部门",
     *     tags={"05.部门管理"},
     *     @OA\Parameter(name="id", in="path", description="部门ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::DEPT_DELETE)]
    public function delete(): \think\response\Json
    {
        $id = $this->getIdParam();
        $this->service(DeptService::class)->delete($id);

        return $this->success(null, '删除成功');
    }
}
