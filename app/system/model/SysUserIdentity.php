<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\BaseModel;

/**
 * 本地用户与外部账号的关联模型
 *
 * @property int    $id                关联记录ID
 * @property int    $userId            本地用户ID
 * @property string $provider          具体认证源标识
 * @property string $providerAccountId 来源方账号ID
 * @property string $displayName       外部账号展示名
 * @property string $avatarUrl         外部账号头像
 * @property string $lastLoginTime     最近一次登录时间
 */
class SysUserIdentity extends BaseModel
{
    protected $name = 'sys_user_identity';

    // sys_user_identity 无 is_deleted 字段，不启用软删除全局作用域
    protected $globalScope = [];

    protected $type = [
        'user_id' => 'integer',
    ];

    // 微信小程序的认证源标识，与配置保持一致
    public const WECHAT_MINI_PROVIDER = 'wechat-mini-main';
}
