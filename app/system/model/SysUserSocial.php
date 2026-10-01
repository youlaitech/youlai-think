<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\BaseModel;

/**
 * 用户社交账号绑定模型
 *
 * @property int    $id          主键ID
 * @property int    $userId      用户ID
 * @property string $platform    平台标识
 * @property string $openid     OpenID
 * @property string $unionid    UnionID
 * @property string $sessionKey 会话密钥
 * @property int    $verified    是否已验证
 */
class SysUserSocial extends BaseModel
{
    protected $name = 'sys_user_social';

    // sys_user_social 无 is_deleted 字段，不启用软删除全局作用域
    protected $globalScope = [];

    protected $type = [
        'user_id' => 'integer',
        'verified' => 'integer',
    ];
}
