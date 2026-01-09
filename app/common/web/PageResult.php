<?php

declare(strict_types=1);

namespace app\common\web;

final class PageResult
{
    public function __construct(
        public string $code,
        public array $data,
        public array $page,
        public string $msg,
    ) {
    }

    public static function success(array $list, int $total, int $pageNum, int $pageSize): self
    {
        return new self(
            ResultCode::SUCCESS->getCode(),
            $list,
            [
                'pageNum' => $pageNum,
                'pageSize' => $pageSize,
                'total' => $total,
            ],
            ResultCode::SUCCESS->getMsg(),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'data' => $this->data,
            'page' => $this->page,
            'msg' => $this->msg,
        ];
    }
}
