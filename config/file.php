<?php declare(strict_types=1);

// 文件存储
return [
    // s3 | local | aliyun
    'type' => env('FILE_TYPE', 's3'),

    'upload' => [
        // 如 50MB
        'max-file-size' => env('FILE_MAX_SIZE', '50MB'),
        // 置空表示不限制
        'allowed-extensions' => ['jpg', 'jpeg', 'png', 'gif'],
    ],

    // S3（RustFS）
    's3' => [
        'endpoint'   => env('S3_ENDPOINT', 'http://127.0.0.1:9000'),
        'access-key' => env('S3_ACCESS_KEY', 'rustfs-admin'),
        'secret-key' => env('S3_SECRET_KEY', 'rustfs-admin'),
        'bucket'     => env('S3_BUCKET', 'public'),
        // 配置后文件 URL 走域名，留空则用 endpoint
        'domain'     => env('S3_DOMAIN', ''),
    ],

    'aliyun' => [
        // 如 oss-cn-hangzhou.aliyuncs.com
        'endpoint'          => env('ALIYUN_OSS_ENDPOINT', ''),
        'access-key-id'     => env('ALIYUN_OSS_ACCESS_KEY_ID', ''),
        'access-key-secret' => env('ALIYUN_OSS_ACCESS_KEY_SECRET', ''),
        'bucket'            => env('ALIYUN_OSS_BUCKET', ''),
        // 配置后文件 URL 走域名，留空则用 endpoint
        'domain'            => env('ALIYUN_OSS_DOMAIN', ''),
    ],

    'local' => [
        'path'     => env('LOCAL_PATH', app()->getRootPath() . 'public/storage'),
        'base-url' => env('LOCAL_BASE_URL', '/storage'),
    ],
];
