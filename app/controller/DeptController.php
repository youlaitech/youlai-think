<?php declare(strict_types=1);

namespace app\controller;

use app\controller\ApiController;
use app\service\DeptService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="05.部门管理")
 */
final class DeptController extends ApiController
{
    /**
     * 获取部门�?
     */
    public function tree(): \think\response\Json
    {
        $tree = $this->service(DeptService::class)->getTree();

        return $this->success($tree);
    }

    /**
     * 获取所有部门（平铺列表�?
     */
    public function list(): \think\response\Json
    {
        $list = $this->service(DeptService::class)->getAll();

        return $this->success($list);
    }

    /**
     * 获取部门详情
     */
    public function detail(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(DeptService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '部门不存�?);
        }

        return $this->success($data);
    }

    /**
     * 创建部门
     */
    public function create(): \think\response\Json
    {
        $this->checkDemo();

        $id = $this->service(DeptService::class)->create($this->getAllParams());

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新部门
     */
    public function update(): \think\response\Json
    {
        $this->checkDemo();

        $id = $this->getIdParam();
        $this->service(DeptService::class)->update($id, $this->getAllParams());

        return $this->success(null, '更新成功');
    }

    /**
     * 删除部门
     */
    public function delete(): \think\response\Json
    {
        $this->checkDemo();

        $id = $this->getIdParam();
        $this->service(DeptService::class)->delete($id);

        return $this->success(null, '删除成功');
    }
}
