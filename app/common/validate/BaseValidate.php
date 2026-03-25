<?php declare(strict_types=1);

namespace app\common\validate;

use think\Validate;

/**
 * 验证器基类
 */
abstract class BaseValidate extends Validate
{
    /**
     * 验证并抛出异常
     */
    public function checkOrFail(array $data): array
    {
        if (!$this->check($data)) {
            throw new \think\exception\ValidateException($this->getError());
        }
        return $data;
    }
}
