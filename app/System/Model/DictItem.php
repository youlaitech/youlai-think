<?php declare(strict_types=1);

namespace app\System\Model;

use app\common\model\Model;

/**
 * 字典项模型
 *
 * @property int    $id          字典项ID
 * @property string $dictCode    字典编码
 * @property string $label       标签
 * @property string $value       值
 * @property int    $sort        排序
 * @property int    $status      状态
 *
 * @property Dict   $dict        所属字典
 */
class DictItem extends Model
{
    protected $name = 'sys_dict_item';

    protected $type = [
        'id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 所属字典（通过 dict_code 关联）
     */
    public function dict(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Dict::class, 'dict_code', 'dict_code');
    }

    /**
     * 启用状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
