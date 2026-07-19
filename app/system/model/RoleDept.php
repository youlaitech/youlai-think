<?php declare(strict_types=1);

namespace app\system\model;

use think\model\Pivot;

class RoleDept extends Pivot
{
    protected $name = 'sys_role_dept';
    protected $autoWriteTimestamp = false;
}
