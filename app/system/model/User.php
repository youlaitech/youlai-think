<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\BaseModel;

/**
 * 用户模型。
 */
class User extends BaseModel
{
    protected $name = 'sys_user';

    // 类型转换
    protected $type = [
        'id' => 'integer',
        'gender' => 'integer',
        'status' => 'integer',
        'dept_id' => 'integer',
    ];

    /**
     * 所属部门
     */
    public function dept(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Dept::class, 'dept_id', 'id');
    }

    /**
     * 关联角色（多对多）
     */
    public function roles(): \think\model\relation\BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            UserRole::class,
            'role_id',
            'user_id'
        );
    }

    /**
     * 部门ID访问器
     */
    public function getDeptIdAttr(mixed $value): string
    {
        return (string) $value;
    }

    /**
     * 性别文本
     */
    public function getGenderTextAttr(mixed $value, array $data): string
    {
        return match ((int) ($data['gender'] ?? 0)) {
            1 => '男',
            2 => '女',
            default => '未知',
        };
    }

    /**
     * 状态文本
     */
    public function getStatusTextAttr(mixed $value, array $data): string
    {
        return (int) ($data['status'] ?? 0) === 1 ? '启用' : '禁用';
    }

    /**
     * 启用状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 按部门查询
     */
    public function scopeByDept($query, int $deptId)
    {
        return $query->where('dept_id', $deptId);
    }
}
