<?php

// +----------------------------------------------------------------------
// | 服务提供配置
// +----------------------------------------------------------------------

use extend\http\HttpClient;
use think\exception\Handle;

return [
    HttpClient::class => HttpClient::class,
    Handle::class => \app\ExceptionHandle::class,
];
