<?php

declare(strict_types=1);

namespace app\{$moduleName}\controller;

use app\controller\BaseController;
use app\{$moduleName}\service\{$entityName}Service;

/**
 * {$businessName}接口
 */
final class {$entityName}Controller extends BaseController
{
    /**
     * 分页
     */
    public function page(): \think\Response
    {
        [$list, $total] = (new {$entityName}Service())->page($this->request->param());
        return $this->success($list, $total);
    }

    /**
     * 表单
     */
    public function form(int $id): \think\Response
    {
        $data = (new {$entityName}Service())->getFormData($id);
        return $this->success($data);
    }

    /**
     * 新增
     */
    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new {$entityName}Service())->create($data);
        return $this->success();
    }

    /**
     * 修改
     */
    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new {$entityName}Service())->update($id, $data);
        return $this->success();
    }

    /**
     * 删除
     */
    public function delete(string $ids): \think\Response
    {
        (new {$entityName}Service())->delete($ids);
        return $this->success();
    }
}
