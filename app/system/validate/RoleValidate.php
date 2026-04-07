<?php

declare(strict_types=1);

namespace app\system\validate;

use app\common\validate\BaseValidate;

/**
 * 角色验证器
 */
class RoleValidate extends BaseValidate
{
    protected $rule = [
        'name' => 'require|length:1,30',
        'code' => 'require|alphaDash|length:1,30',
        'status' => 'in:0,1',
        'sort' => 'integer|between:0,999',
        'menu_ids' => 'array',
    ];

    protected $message = [
        'name.require' => '角色名称不能为空',
        'name.length' => '角色名称长度为1-30个字符',
        'code.require' => '角色编码不能为空',
        'code.alphaDash' => '角色编码只能包含字母、数字、下划线和短横线',
        'code.length' => '角色编码长度为1-30个字符',
        'status.in' => '状态值不正确',
        'sort.integer' => '排序必须为整数',
        'sort.between' => '排序范围为0-999',
    ];

    protected function sceneCreate(): RoleValidate
    {
        return $this->only(['name', 'code', 'status', 'sort', 'menu_ids']);
    }

    protected function sceneUpdate(): RoleValidate
    {
        return $this->only(['name', 'status', 'sort', 'menu_ids']);
    }
}
