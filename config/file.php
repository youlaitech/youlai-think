<?php

// 文件存储配置（结构参照 youlai-boot 的 file-storage）
return [
    // 存储类型：minio | local
    'type' => 'minio',

    // 上传限制
    'upload' => [
        // 单文件大小上限（DataSize 字符串，如 50MB）
        'max-file-size' => '50MB',
        // 允许的文件扩展名白名单（置空表示不限制）
        'allowed-extensions' => ['jpg', 'jpeg', 'png', 'gif'],
    ],

    // MinIO 对象存储
    'minio' => [
        'endpoint'   => 'http://111.229.83.153:9000',
        'access-key' => 'bybaddp7zyARpgNbEGKf',
        'secret-key' => 'p9rBdQZPBIJcMH23iyFkZkXmmawbmwPlk3JLlaaj',
        'bucket'     => 'public',
        // 自定义域名：配置后文件 URL 走域名，留空则用 endpoint（IP）格式
        'domain'     => '',
    ],

    // 本地存储
    'local' => [
        'path'     => app()->getRootPath() . 'public/storage',
        'base-url' => '/storage',
    ],
];
