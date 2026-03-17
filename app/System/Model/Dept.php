<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\Model;

/**
 * 部门模型
 *
 * @property int    $id          部门ID
 * @property int    $parentId    父部门ID
 * @property string $name        部门名称
 * @property string $code        部门编码
 * @property int    $sort        排序
 * @property int    $status      状态
 * @property string $treePath    树路径（逗号分隔的ID链）
 * @property string $createTime  创建时间
 *
 * @property Dept   $parent      父部门
 * @property Dept[] $children    子部门
 * @property User[] $users       部门用户
 */
class Dept extends Model
{
    protected $name = 'sys_dept';

    protected $type = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
    ];

    // ==================== 关联关系 ====================

    /**
     * 父部门
     */
    public function parent(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 子部门
     */
    public function children(): \think\model\relation\HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->order('sort', 'asc');
    }

    /**
     * 部门用户
     */
    public function users(): \think\model\relation\HasMany
    {
        return $this->hasMany(User::class, 'dept_id', 'id');
    }

    // ==================== 访问器 ====================

    /**
     * 父ID访问器
     */
    public function getParentIdAttr(mixed $value): string
    {
        return (string) $value;
    }

    /**
     * 状态文本
     */
    public function getStatusTextAttr(mixed $value, array $data): string
    {
        return (int) ($data['status'] ?? 0) === 1 ? '启用' : '禁用';
    }

    // ==================== 查询作用域 ====================

    /**
     * 启用状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 按编码查询
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    // ==================== 业务方法 ====================

    /**
     * 获取所有子部门ID（包含自己）
     */
    public function getDescendantIds(): array
    {
        $ids = [$this->id];
        $children = $this->children;

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->getDescendantIds());
        }

        return $ids;
    }
}
