<?php declare(strict_types=1);

namespace app\System\Model;

use think\model\Pivot;

/**
 * 用户角色关联表（中间表模型必须继承 Pivot）
 */
class UserRole extends Pivot
{
    protected $name = 'sys_user_role';

    protected $autoWriteTimestamp = false;
}
