<?php declare(strict_types=1);

namespace app\form\model;

use app\common\model\BaseModel;

/**
 * 表单版本快照模型（form_snapshot）：发布时固化规则，只增不改
 *
 * @property int $id
 * @property int $form_id    表单定义ID
 * @property int $version    版本号
 * @property mixed $form_json 表单规则快照
 */
class FormSnapshot extends BaseModel
{
    protected $name = 'form_snapshot';

    protected $deleteTime = false;

    protected $type = [
        'form_id' => 'integer',
        'version' => 'integer',
        'form_json' => 'json',
        'options_json' => 'json',
        'is_deleted' => 'integer',
    ];
}
