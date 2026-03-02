<?php declare(strict_types=1);

namespace app\model;

/**
 * 用户角色关联表
 */
class UserRole extends Model
{
    protected $name = 'sys_user_role';

    // 不自动写入时间戳
    protected $autoWriteTimestamp = false;

    // 无软删除
    protected $deleteTime = false;
}
