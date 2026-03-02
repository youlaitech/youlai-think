<?php declare(strict_types=1);

namespace app\common\web;

/**
 * 错误码接口
 */
interface IResultCode
{
    /**
     * 获取错误码
     */
    public function getCode(): string;

    /**
     * 获取错误消息
     */
    public function getMsg(): string;
}
