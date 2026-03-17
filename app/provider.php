<?php

// +----------------------------------------------------------------------
// | 服务提供配置
// +----------------------------------------------------------------------

use app\common\http\HttpClient;
use app\common\websocket\StompHandler;
use app\common\websocket\UserSessionRegistry;

return [
    HttpClient::class => HttpClient::class,
    UserSessionRegistry::class => UserSessionRegistry::class,
    StompHandler::class => StompHandler::class,
];
