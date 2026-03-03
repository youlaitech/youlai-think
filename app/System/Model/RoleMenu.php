<?php declare(strict_types=1);

namespace app\System\Model;

use think\model\Pivot;

/**
 * 角色菜单关联表（中间表模型必须继承 Pivot）
 */
class RoleMenu extends Pivot
{
    protected $name = 'sys_role_menu';

    protected $autoWriteTimestamp = false;
}
