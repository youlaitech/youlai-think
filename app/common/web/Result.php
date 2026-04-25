<?php declare(strict_types=1);

namespace app\common\web;

/**
 * 接口响应对象 { code, data, msg }
 */
final class Result
{
    public function __construct(
        public string $code,
        public mixed $data,
        public string $msg,
        public ?string $traceId = null,
    ) {}

    /**
     * 成功响应
     */
    public static function success(mixed $data = null, string $msg = ''): self
    {
        return new self(
            ResultCode::SUCCESS->getCode(),
            $data,
            $msg ?: ResultCode::SUCCESS->getMsg()
        );
    }

    /**
     * 分页成功响应
     */
    public static function page(array $list, int $total): self
    {
        return new self(
            ResultCode::SUCCESS->getCode(),
            ['list' => $list, 'total' => $total],
            ResultCode::SUCCESS->getMsg()
        );
    }

    /**
     * 失败响应
     */
    public static function failed(?string $msg = null): self
    {
        $rc = ResultCode::SYSTEM_ERROR;
        return new self($rc->getCode(), null, $msg ?: $rc->getMsg());
    }

    /**
     * 指定错误码的失败响应
     */
    public static function failedWith(IResultCode $resultCode, ?string $msg = null, mixed $data = null): self
    {
        return new self(
            $resultCode->getCode(),
            $data,
            $msg ?: $resultCode->getMsg()
        );
    }

    /**
     * 布尔判断响应
     */
    public static function judge(bool $status): self
    {
        return $status ? self::success() : self::failed();
    }

    /**
     * 设置追踪ID
     */
    public function withTraceId(string $traceId): self
    {
        $this->traceId = $traceId;
        return $this;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'code' => $this->code,
            'data' => $this->data,
            'msg' => $this->msg,
        ];

        if ($this->traceId !== null) {
            $result['traceId'] = $this->traceId;
        }

        return $result;
    }
}
