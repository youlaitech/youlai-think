<?php

declare(strict_types=1);

namespace app\System\Validate;

use app\common\validate\BaseValidate;

/**
 * 用户验证器。
 */
class UserValidate extends BaseValidate
{
    protected $rule = [
        'username' => 'require|alphaNum|length:3,20',
        'password' => 'require|length:6,20',
        'nickname' => 'require|length:1,30',
        'mobile' => 'isMobile',
        'email' => 'email',
        'status' => 'in:0,1',
        'gender' => 'in:0,1,2',
        'dept_id' => 'integer',
        'role_ids' => 'array',
    ];

    protected $message = [
        'username.require' => '用户名不能为空',
        'username.alphaNum' => '用户名只能包含字母和数字',
        'username.length' => '用户名长度为3-20个字符',
        'password.require' => '密码不能为空',
        'password.length' => '密码长度为6-20个字符',
        'nickname.require' => '昵称不能为空',
        'nickname.length' => '昵称长度为1-30个字符',
        'mobile.isMobile' => '手机号格式不正确',
        'email.email' => '邮箱格式不正确',
        'status.in' => '状态值不正确',
        'gender.in' => '性别值不正确',
    ];

    /**
     * 登录验证场景
     */
    protected function sceneLogin(): UserValidate
    {
        return $this->only(['username', 'password']);
    }

    /**
     * 创建用户验证场景
     */
    protected function sceneCreate(): UserValidate
    {
        return $this->only(['username', 'password', 'nickname', 'mobile', 'email', 'status', 'gender', 'dept_id', 'role_ids']);
    }

    /**
     * 更新用户验证场景
     */
    protected function sceneUpdate(): UserValidate
    {
        return $this->only(['nickname', 'mobile', 'email', 'status', 'gender', 'dept_id', 'role_ids'])
            ->remove('nickname', 'require');
    }
}
