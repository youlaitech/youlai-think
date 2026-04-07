<?php declare(strict_types=1);

namespace app\common\exception;

use app\common\web\IResultCode;
use app\common\web\ResultCode;

/**
 * 业务异常
 * 支持两种调用方式：
 * - throw new BusinessException('消息')：默认使用 SYSTEM_ERROR (B0001)
 * - throw new BusinessException(ResultCode::XXX, '消息')：指定错误码
 */
final class BusinessException extends \RuntimeException
{
    private readonly IResultCode $resultCode;

    public function __construct(
        IResultCode|string $resultCodeOrMessage = ResultCode::SYSTEM_ERROR,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if (is_string($resultCodeOrMessage)) {
            // 第一个参数是字符串，当作 message 处理，默认使用 SYSTEM_ERROR
            $this->resultCode = ResultCode::SYSTEM_ERROR;
            parent::__construct($resultCodeOrMessage !== '' ? $resultCodeOrMessage : $this->resultCode->getMsg(), $code, $previous);
        } else {
            // 第一个参数是 ResultCode
            $this->resultCode = $resultCodeOrMessage;
            parent::__construct($message !== '' ? $message : $resultCodeOrMessage->getMsg(), $code, $previous);
        }
    }

    public function getResultCode(): IResultCode
    {
        return $this->resultCode;
    }
}
