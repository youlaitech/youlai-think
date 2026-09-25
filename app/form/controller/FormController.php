<?php declare(strict_types=1);

namespace app\form\controller;

use app\controller\BaseController;
use app\form\service\FormService;
use think\response\Json;

/**
 * 动态表单接口（表单定义、渲染、数据、访问菜单生成、AI 生成）
 */
class FormController extends BaseController
{
    /**
     * 表单分页列表
     */
    public function page(): Json
    {
        return $this->success($this->service(FormService::class)->getPage($this->getAllParams()));
    }

    /**
     * 新增表单
     */
    public function create(): Json
    {
        $id = $this->service(FormService::class)->create($this->getAllParams(), $this->getAuthUserId());
        return $this->success(['id' => (string) $id]);
    }

    /**
     * 审批表单下拉选项
     */
    public function workflowOptions(): Json
    {
        return $this->success($this->service(FormService::class)->workflowOptions());
    }

    /**
     * AI 生成表单规则
     */
    public function aiGenerate(): Json
    {
        $description = trim((string) $this->getParam('description', ''));
        return $this->success($this->service(FormService::class)->aiGenerateRule($description));
    }

    /**
     * 公开表单渲染规则（匿名）
     */
    public function publicRender(string $form_key): Json
    {
        return $this->success($this->service(FormService::class)->publicRender($form_key));
    }

    /**
     * 公开表单提交（匿名）
     */
    public function publicSubmit(string $form_key): Json
    {
        $this->service(FormService::class)->submitPublic($form_key, $this->getAllParams(), (string) request()->ip());
        return $this->success();
    }

    /**
     * 表单设计数据
     */
    public function getForm(int $id): Json
    {
        return $this->success($this->service(FormService::class)->getForm($id));
    }

    /**
     * 修改表单
     */
    public function update(int $id): Json
    {
        $this->service(FormService::class)->update($id, $this->getAllParams(), $this->getAuthUserId());
        return $this->success();
    }

    /**
     * 删除表单
     */
    public function remove(string $ids): Json
    {
        $this->service(FormService::class)->remove($ids);
        return $this->success();
    }

    /**
     * 发布表单
     */
    public function publish(int $id): Json
    {
        $this->service(FormService::class)->publish($id);
        return $this->success();
    }

    /**
     * 停用表单
     */
    public function disable(int $id): Json
    {
        $this->service(FormService::class)->disable($id);
        return $this->success();
    }

    /**
     * 表单访问菜单回显
     */
    public function getMenu(int $id): Json
    {
        return $this->success($this->service(FormService::class)->getMenu($id));
    }

    /**
     * 生成表单访问菜单
     */
    public function saveMenu(int $id): Json
    {
        $this->service(FormService::class)->saveMenu($id, $this->getAllParams(), $this->getAuthUserId());
        return $this->success();
    }

    /**
     * 表单渲染规则
     */
    public function render(string $form_key): Json
    {
        return $this->success($this->service(FormService::class)->render($form_key));
    }

    /**
     * 表单数据分页
     */
    public function dataPage(string $form_key): Json
    {
        $pageNum = (int) $this->getParam('page_num', 1);
        $pageSize = (int) $this->getParam('page_size', 10);
        return $this->success($this->service(FormService::class)->getDataPage($form_key, $pageNum, $pageSize));
    }

    /**
     * 提交表单数据
     */
    public function submitData(string $form_key): Json
    {
        $this->service(FormService::class)->submit($form_key, $this->getAllParams(), $this->getAuthUserId());
        return $this->success();
    }

    /**
     * 表单数据详情
     */
    public function dataDetail(string $form_key, int $data_id): Json
    {
        return $this->success($this->service(FormService::class)->getDataDetail($data_id));
    }

    /**
     * 删除表单数据
     */
    public function deleteData(string $form_key, string $ids): Json
    {
        $this->service(FormService::class)->deleteData($ids);
        return $this->success();
    }
}
