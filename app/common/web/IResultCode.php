<?php declare(strict_types=1);

namespace app\common\web;

/**
 * 响应码接口
 */
interface IResultCode
{
    /** 获取错误码 */
    public function getCode(): string;

    /** 获取错误信息 */
    public function getMsg(): string;
}
