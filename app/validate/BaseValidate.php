<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 验证器基类
 *
 * 提供统一的验证规则和自定义错误消息
 */
abstract class BaseValidate extends Validate
{
    /**
     * 自定义验证规则：手机号
     */
    protected function isMobile(string $value): bool
    {
        return (bool) preg_match('/^1[3-9]\d{9}$/', $value);
    }

    /**
     * 自定义验证规则：身份证号
     */
    protected function isIdCard(string $value): bool
    {
        return (bool) preg_match('/^\d{17}[\dXx]$/', $value);
    }

    /**
     * 验证并抛出异常
     *
     * @throws \app\common\exception\BusinessException
     */
    public function checkOrFail(array $data): array
    {
        if (!$this->check($data)) {
            throw new \app\common\exception\BusinessException(
                \app\common\web\ResultCode::USER_REQUEST_PARAMETER_ERROR,
                $this->getError()
            );
        }

        return $this->validated;
    }
}
