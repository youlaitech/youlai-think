<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

// 角色模型，对应 sys_role
class Role extends Model
{
    protected $name = 'sys_role';
}
