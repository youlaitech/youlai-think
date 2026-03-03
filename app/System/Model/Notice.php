<?php declare(strict_types=1);

namespace app\System\Model;

use app\common\model\Model;

/**
 * 通知公告模型
 *
 * @property int    $id           公告ID
 * @property string $title        标题
 * @property string $content      内容
 * @property int    $type         类型 1通知 2公告
 * @property int    $publishStatus 发布状态 0草稿 1已发布
 * @property string $publishTime  发布时间
 * @property int    $createBy     创建人ID
 * @property string $createTime   创建时间
 *
 * @property User   $creator      创建人
 */
class Notice extends Model
{
    protected $name = 'sys_notice';

    protected $type = [
        'id' => 'integer',
        'type' => 'integer',
        'publish_status' => 'integer',
        'create_by' => 'integer',
    ];

    /**
     * 创建人
     */
    public function creator(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(User::class, 'create_by', 'id');
    }

    /**
     * 已发布
     */
    public function scopePublished($query)
    {
        return $query->where('publish_status', 1);
    }
}
