<?php declare(strict_types=1);

namespace app\form\model;

use app\common\model\BaseModel;

/**
 * 表单定义模型（form_definition）
 *
 * @property int    $id
 * @property string $form_key    表单唯一标识
 * @property string $form_name   表单名称
 * @property mixed  $form_json   表单规则
 * @property int    $status      0草稿 1已发布 -1已停用
 * @property int    $is_public   是否允许匿名公开访问
 * @property string $category    normal通用表单 workflow审批表单
 * @property mixed  $menu_id     生成的访问菜单ID
 * @property int    $version     版本号
 */
class FormDefinition extends BaseModel
{
    protected $name = 'form_definition';

    // 表无 delete_time 列，逻辑删除走 BaseModel 的 is_deleted 全局作用域
    protected $deleteTime = false;

    protected $type = [
        'form_json' => 'json',
        'options_json' => 'json',
        'status' => 'integer',
        'is_public' => 'integer',
        'version' => 'integer',
        'is_deleted' => 'integer',
    ];
}
