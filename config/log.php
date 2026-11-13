<?php declare(strict_types=1);

return [
    'default'      => 'file',
    'level'        => [],
    // 按日志类型指定通道，如 ['error' => 'email']
    'type_channel' => [],
    'close'        => false,
    'processor'    => null,

    'channels'     => [
        'file' => [
            'type'           => 'File',
            'path'           => '',
            'single'         => false,
            'apart_level'    => [],
            'max_files'      => 0,
            'json'           => false,
            'processor'      => null,
            'close'          => false,
            'format'         => '[%s][%s] %s',
            'realtime_write' => false,
        ],
    ],
];
