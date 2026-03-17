<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\Model;

/**
 * 系统配置模型
 *
 * @property int    $id          配置ID
 * @property string $key         配置键
 * @property string $value       配置值
 * @property string $name        配置名称
 * @property string $remark      备注
 * @property string $createTime  创建时间
 */
class Config extends Model
{
    protected $name = 'sys_config';

    protected $type = [
        'id' => 'integer',
    ];

    // 无软删除
    protected $deleteTime = false;
}
