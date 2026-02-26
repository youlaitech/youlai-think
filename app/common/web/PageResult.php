<?php declare(strict_types=1);

namespace app\common\web;

/**
 * 分页响应封装
 */
final class PageResult
{
    public function __construct(
        public string $code,
        public array $data,
        public string $msg,
        public ?string $traceId = null,
    ) {
    }

    public static function success(array $list, int $total, string $msg = ''): self
    {
        return new self(
            ResultCode::SUCCESS->getCode(),
            [
                'list' => $list,
                'total' => $total,
            ],
            $msg ?: ResultCode::SUCCESS->getMsg()
        );
    }

    public function withTraceId(string $traceId): self
    {
        $this->traceId = $traceId;
        return $this;
    }

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
