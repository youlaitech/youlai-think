<?php

use app\common\websocket\StompHandler;

return [
    // HTTP 服务（与 WebSocket 共用端口）
    'http'       => [
        'enable'     => true,
        'host'       => '0.0.0.0',
        'port'       => env('HTTP_PORT', 8000),
        'worker_num' => 4,
        'options'    => [],
    ],
    // WebSocket 服务（STOMP 协议，路径: /ws）
    'websocket'  => [
        'enable'        => true,
        'handler'       => StompHandler::class,
        'path'          => '/ws',
        'ping_interval' => 25000,
        'ping_timeout'  => 60000,
    ],
    'queue'      => [
        'enable'  => false,
        'workers' => [],
    ],
    'hot_update' => [
        'enable'  => env('APP_DEBUG', false),
        'name'    => ['*.php'],
        'include' => [app_path(), config_path(), root_path('route')],
        'exclude' => [],
    ],
];
