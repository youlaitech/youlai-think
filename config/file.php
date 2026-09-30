<?php

// 文件存储配置
return [
    // 存储类型：s3 | local | aliyun（走环境变量，默认 s3）
    'type' => env('FILE_TYPE', 's3'),

    // 上传限制
    'upload' => [
        // 单文件大小上限（DataSize 字符串，如 50MB）
        'max-file-size' => env('FILE_MAX_SIZE', '50MB'),
        // 允许的文件扩展名白名单（置空表示不限制）
        'allowed-extensions' => ['jpg', 'jpeg', 'png', 'gif'],
    ],

    // S3（RustFS）对象存储
    's3' => [
        'endpoint'   => env('S3_ENDPOINT', 'http://127.0.0.1:9000'),
        'access-key' => env('S3_ACCESS_KEY', 'rustfs-admin'),
        'secret-key' => env('S3_SECRET_KEY', 'rustfs-admin'),
        'bucket'     => env('S3_BUCKET', 'public'),
        // 自定义域名：配置后文件 URL 走域名，留空则用 endpoint（IP）格式
        'domain'     => env('S3_DOMAIN', ''),
    ],

    // 阿里云 OSS 对象存储
    'aliyun' => [
        'endpoint'          => env('ALIYUN_OSS_ENDPOINT', ''),        // 如 oss-cn-hangzhou.aliyuncs.com
        'access-key-id'     => env('ALIYUN_OSS_ACCESS_KEY_ID', ''),
        'access-key-secret' => env('ALIYUN_OSS_ACCESS_KEY_SECRET', ''),
        'bucket'            => env('ALIYUN_OSS_BUCKET', ''),
        // 自定义域名：配置后文件 URL 走域名，留空则用 endpoint 格式
        'domain'            => env('ALIYUN_OSS_DOMAIN', ''),
    ],

    // 本地存储
    'local' => [
        'path'     => env('LOCAL_PATH', app()->getRootPath() . 'public/storage'),
        'base-url' => env('LOCAL_BASE_URL', '/storage'),
    ],
];
