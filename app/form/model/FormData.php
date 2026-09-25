<?php declare(strict_types=1);

namespace app\form\model;

use app\common\model\BaseModel;

/**
 * 表单数据模型（form_data）
 *
 * @property int $id
 * @property int $form_id      表单定义ID
 * @property int $form_version 提交时表单版本
 * @property mixed $data_json  表单数据
 * @property int $create_by    提交人ID，匿名为空
 */
class FormData extends BaseModel
{
    protected $name = 'form_data';

    protected $deleteTime = false;

    protected $type = [
        'form_id' => 'integer',
        'form_version' => 'integer',
        'data_json' => 'json',
        'is_deleted' => 'integer',
    ];
}
