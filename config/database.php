<?php declare(strict_types=1);

return [
    'default'         => env('DB_DRIVER', 'mysql'),
    'time_query_rule' => [],

    // true 自动识别类型，字符串则指定时间字段类型（int/timestamp/datetime/date）
    'auto_timestamp'  => true,
    'datetime_format' => 'Y-m-d H:i:s',
    // 格式：create_time,update_time
    'datetime_field'  => '',

    'connections'     => [
        'mysql' => [
            'type'            => env('DB_TYPE', 'mysql'),
            'hostname'        => env('DB_HOST', '127.0.0.1'),
            'database'        => env('DB_NAME', 'youlai_admin'),
            'username'        => env('DB_USER', 'root'),
            'password'        => env('DB_PASS', ''),
            'hostport'        => env('DB_PORT', '3306'),
            'params'          => [],
            'charset'         => env('DB_CHARSET', 'utf8mb4'),
            'prefix'          => env('DB_PREFIX', ''),

            // 0 集中式，1 分布式（主从）
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            // SQL 监听只在开启调试时生效
            'trigger_sql'     => env('APP_DEBUG', false),
            'fields_cache'    => false,
        ],
    ],
];
