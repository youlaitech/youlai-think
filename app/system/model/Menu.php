<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\BaseModel;

/**
 * 菜单模型
 *
 * @property int    $id          菜单ID
 * @property int    $parentId    父菜单ID
 * @property string $type        类型 C-目录 M-菜单 E-外链 B-按钮
 * @property string $name        菜单名称
 * @property string $routePath   路由路径
 * @property string $component   组件路径
 * @property string $externalUrl 外链地址
 * @property string $perm        权限标识
 * @property string $icon        图标
 * @property int    $sort        排序
 * @property int    $visible     是否可见
 * @property string $createTime  创建时间
 * @property array  $params      路由参数
 *
 * @property Menu   $parent      父菜单
 * @property Menu[] $children    子菜单
 */
class Menu extends BaseModel
{
    protected $name = 'sys_menu';

    // sys_menu 表无 is_deleted 字段，禁用软删除全局作用域
    protected $deleteTime = false;

    protected $type = [
        'parent_id' => 'integer',
        'sort' => 'integer',
        'visible' => 'integer',
        'keep_alive' => 'integer',
        'always_show' => 'integer',
        'params' => 'json',
    ];

    /**
     * 父菜单
     */
    public function parent(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 子菜单
     */
    public function children(): \think\model\relation\HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->order('sort', 'asc');
    }

    /**
     * 关联角色
     */
    public function roles(): \think\model\relation\BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            RoleMenu::class,
            'role_id',
            'menu_id'
        );
    }

    /**
     * 目录/菜单类型（不含按钮）
     */
    public function scopeMenu($query)
    {
        return $query->whereIn('type', ['C', 'M']);
    }

    /**
     * 按钮类型
     */
    public function scopeButton($query)
    {
        return $query->where('type', 'B');
    }

    /**
     * 可见
     */
    public function scopeVisible($query)
    {
        return $query->where('visible', 1);
    }
}
