<?php declare(strict_types=1);

namespace app\model;

/**
 * 角色菜单关联表
 */
class RoleMenu extends Model
{
    protected $name = 'sys_role_menu';

    protected $autoWriteTimestamp = false;
    protected $deleteTime = false;
}
