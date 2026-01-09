<?php

declare(strict_types=1);

namespace app\common\web;

interface IResultCode
{
    public function getCode(): string;

    public function getMsg(): string;
}
