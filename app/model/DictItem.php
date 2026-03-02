<?php declare(strict_types=1);

namespace app\model;

/**
 * 字典项模型
 *
 * @property int    $id          字典项ID
 * @property int    $dictId      字典ID
 * @property string $label       标签
 * @property string $value       值
 * @property int    $sort        排序
 * @property int    $status      状态
 * @property string $createTime  创建时间
 *
 * @property Dict   $dict        所属字典
 */
class DictItem extends Model
{
    protected $name = 'sys_dict_item';

    protected $type = [
        'id' => 'integer',
        'dict_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 所属字典
     */
    public function dict(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Dict::class, 'dict_id', 'id');
    }

    /**
     * 字典ID访问器
     */
    public function getDictIdAttr(mixed $value): string
    {
        return (string) $value;
    }

    /**
     * 启用状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
