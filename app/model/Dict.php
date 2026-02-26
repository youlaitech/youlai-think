<?php declare(strict_types=1);

namespace app\model;

/**
 * ×ÖµäÄ£ĞÍ
 *
 * @property int    $id          ×ÖµäID
 * @property string $code        ×Öµä±àÂë
 * @property string $name        ×ÖµäÃû³Æ
 * @property int    $status      ×´Ì¬
 * @property string $remark      ±¸×¢
 * @property string $createTime  ´´½¨Ê±¼ä
 *
 * @property DictItem[] $items   ×ÖµäÏî
 */
class Dict extends Model
{
    protected $name = 'sys_dict';

    protected $type = [
        'id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * ×ÖµäÏî
     */
    public function items(): \think\model\relation\HasMany
    {
        return $this->hasMany(DictItem::class, 'dict_id', 'id')
            ->order('sort', 'asc');
    }

    /**
     * ÆôÓÃ×´Ì¬
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
