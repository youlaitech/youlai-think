<?php declare(strict_types=1);

namespace app\model;

/**
 * 字典模型
 *
 * @property int    $id          字典ID
 * @property string $code        字典编码
 * @property string $name        字典名称
 * @property int    $status      状态
 * @property string $remark      备注
 * @property string $createTime  创建时间
 *
 * @property DictItem[] $items   字典项
 */
class Dict extends Model
{
    protected $name = 'sys_dict';

    protected $type = [
        'id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * 字典项
     */
    public function items(): \think\model\relation\HasMany
    {
        return $this->hasMany(DictItem::class, 'dict_id', 'id')
            ->order('sort', 'asc');
    }

    /**
     * 启用状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
