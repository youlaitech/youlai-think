<?php declare(strict_types=1);

namespace app\model;

/**
 * ×ÖµäÏîÄ£ĞÍ
 *
 * @property int    $id          ×ÖµäÏîID
 * @property int    $dictId      ×ÖµäID
 * @property string $label       ±êÇ©
 * @property string $value       Öµ
 * @property int    $sort        ÅÅĞò
 * @property int    $status      ×´Ì¬
 * @property string $createTime  ´´½¨Ê±¼ä
 *
 * @property Dict   $dict        ËùÊô×Öµä
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
     * ËùÊô×Öµä
     */
    public function dict(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Dict::class, 'dict_id', 'id');
    }

    /**
     * ×ÖµäID·ÃÎÊÆ÷
     */
    public function getDictIdAttr(mixed $value): string
    {
        return (string) $value;
    }

    /**
     * ÆôÓÃ×´Ì¬
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
