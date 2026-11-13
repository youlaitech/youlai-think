<?php declare(strict_types=1);

return [
    'default' => 'file',

    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '',
            'prefix'     => '',
            // 0 表示永久缓存
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            // 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
    ],
];
