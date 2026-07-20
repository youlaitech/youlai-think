<?php

declare(strict_types=1);

namespace app\system\validate;

use app\common\validate\BaseValidate;
use app\system\model\User;

/**
 * 用户验证器
 */
class UserValidate extends BaseValidate
{
    protected $rule = [
        'username' => 'require|alphaDash|length:3,20',
        'password' => 'require|length:6,20',
        'nickname' => 'require|length:1,30',
        'mobile'   => 'isMobile',
        'email'    => 'email',
        'status'   => 'in:0,1',
        'gender'   => 'in:0,1,2',
        'dept_id'  => 'exist:sys_dept,id',
        'role_ids' => 'array',
    ];

    protected $message = [
        'username.require' => '用户名不能为空',
        'username.alphaDash' => '用户名只能包含字母、数字、下划线和破折号',
        'username.length' => '用户名长度为3-20个字符',
        'username.unique' => '用户名已存在',
        'password.require' => '密码不能为空',
        'password.length' => '密码长度为6-20个字符',
        'nickname.require' => '昵称不能为空',
        'nickname.length' => '昵称长度为1-30个字符',
        'mobile' => '手机号格式不正确',
        'email' => '邮箱格式不正确',
        'status.in' => '状态值不正确',
        'gender.in' => '性别值不正确',
        'dept_id.exist' => '部门不存在',
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
        return $this->only(['username', 'password', 'nickname', 'mobile', 'email', 'status', 'gender', 'dept_id', 'role_ids'])
            // 用户名唯一性需忽略软删除记录，避免逻辑删除的残留记录误拦截用户重建
            // 注意：append 的闭包规则必须以数组形式传入，否则 thinkphp 在
            // array_merge($rules, $this->append[$field]) 处抛 TypeError，导致创建用户 500。
            ->append('username', [function ($value) {
                $exists = User::where('username', $value)->where('is_deleted', 0)->find();
                return $exists ? '用户名已存在' : true;
            }]);
    }

    /**
     * 更新用户验证场景
     */
    protected function sceneUpdate(): UserValidate
    {
        return $this->only(['nickname', 'mobile', 'email', 'avatar', 'status', 'gender', 'dept_id', 'role_ids'])
            ->remove('nickname', 'require');
    }
}
