<?php declare(strict_types=1);

namespace app\common\exception;

use app\common\web\IResultCode;
use app\common\web\ResultCode;

/**
 * 业务异常，只传字符串时按无效输入处理，传 ResultCode 则走指定错误码
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
            // 纯字符串消息，默认按无效输入处理（对应 Spring Assert.isTrue 行为）
            $this->resultCode = ResultCode::INVALID_USER_INPUT;
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
