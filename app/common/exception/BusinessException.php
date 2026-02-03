<?php

declare(strict_types=1);

namespace app\common\exception;

use app\common\web\IResultCode;
use app\common\web\ResultCode;

class BusinessException extends \RuntimeException
{
    public function __construct(
        private readonly IResultCode $resultCode = ResultCode::SYSTEM_ERROR,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        // 未传 message 时使用枚举默认提示
        parent::__construct($message !== '' ? $message : $resultCode->getMsg(), $code, $previous);
    }

    public function getResultCode(): IResultCode
    {
        return $this->resultCode;
    }
}
