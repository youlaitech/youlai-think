<?php

declare(strict_types=1);

namespace app\common\web;

final class Result
{
    public function __construct(
        public string $code,
        public mixed $data,
        public string $msg,
    ) {
    }

    public static function success(mixed $data = null): self
    {
        // 成功响应统一结构
        return new self(ResultCode::SUCCESS->getCode(), $data, ResultCode::SUCCESS->getMsg());
    }

    public static function failed(?string $msg = null): self
    {
        // 失败响应默认使用系统错误码
        $rc = ResultCode::SYSTEM_ERROR;
        return new self($rc->getCode(), null, $msg !== null && $msg !== '' ? $msg : $rc->getMsg());
    }

    public static function judge(bool $status): self
    {
        // 常用于保存/更新等布尔结果
        return $status ? self::success() : self::failed();
    }

    public static function failedWith(IResultCode $resultCode, ?string $msg = null, mixed $data = null): self
    {
        // 自定义业务错误返回
        $finalMsg = $msg !== null && $msg !== '' ? $msg : $resultCode->getMsg();
        return new self($resultCode->getCode(), $data, $finalMsg);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'data' => $this->data,
            'msg' => $this->msg,
        ];
    }
}
