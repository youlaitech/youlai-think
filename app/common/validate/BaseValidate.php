<?php declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

/**
 * 验证器基类
 */
abstract class BaseValidate extends Validate
{
    /**
     * 验证数据，失败时抛 ValidateException
     */
    public function checkOrFail(array $data): array
    {
        if (!$this->check($data)) {
            throw new \think\exception\ValidateException($this->getError());
        }
        return $data;
    }

    /**
     * 中国大陆手机号校验（空值通过）
     */
    protected function isMobile(mixed $value, mixed $rule = null, array $data = [], string $field = ''): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $value = (string) $value;
        return (bool) preg_match('/^1[3-9]\d{9}$/', $value);
    }
}
