<?php

// +----------------------------------------------------------------------
// | 队列配置
// +----------------------------------------------------------------------

return [
    // 默认队列驱动
    'default' => 'redis',

    // 队列连接配置
    'connections' => [
        // 同步执行（开发调试用）
        'sync' => [
            'type' => 'sync',
        ],

        // Redis驱动（推荐生产环境使用）
        'redis' => [
            'type' => 'redis',
            'queue' => 'default',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASS', ''),
            'select' => env('REDIS_SELECT', 0),
            'timeout' => 0,
            'persistent' => false,
        ],

        // 数据库驱动（无Redis时的备选方案）
        'database' => [
            'type' => 'database',
            'queue' => 'default',
            'table' => 'jobs',
            'connection' => null,
        ],
    ],

    // 任务失败处理
    'failed' => [
        // 失败任务记录表
        'table' => 'failed_jobs',
        // 失败任务最大重试次数
        'max_retries' => 3,
        // 重试间隔（秒）
        'retry_after' => 60,
    ],
];
