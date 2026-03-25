<?php declare(strict_types=1);

namespace app\common\web;

/**
 * 响应状态码接口
 */
interface IResultCode
{
    public function getCode(): string;

    public function getMsg(): string;
}
