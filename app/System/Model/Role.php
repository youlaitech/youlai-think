<?php declare(strict_types=1);

namespace app\System\Model;

/**
 * 角色模型。
 */
class Role extends Model
{
    protected $name = 'sys_role';

    protected $type = [
        'id' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
    ];

    // ==================== 关联关系 ====================

    /**
     * 关联用户
     */
    public function users(): \think\model\relation\BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            UserRole::class,
            'user_id',
            'role_id'
        );
    }

    /**
     * 关联菜单
     */
    public function menus(): \think\model\relation\BelongsToMany
    {
        return $this->belongsToMany(
            Menu::class,
            RoleMenu::class,
            'menu_id',
            'role_id'
        );
    }

    // ==================== 访问器 ====================

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

    /**
     * 是否超级管理员
     */
    public function isSuperAdmin(): bool
    {
        return $this->code === 'ROOT';
    }
}
