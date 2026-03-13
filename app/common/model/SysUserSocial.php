<?php declare(strict_types=1);

namespace app\common\model;

/**
 * 用户第三方账号绑定模型
 */
final class SysUserSocial extends Model
{
    // 设置表名
    protected $name = 'sys_user_social';

    // 主键
    protected $pk = 'id';

    // 自动写入时间戳字段
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
}
