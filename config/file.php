<?php

// 文件存储配置
return [
    // 存储类型：minio | local | aliyun（走环境变量，默认 minio）
    'type' => env('FILE_TYPE', 'minio'),

    // 上传限制
    'upload' => [
        // 单文件大小上限（DataSize 字符串，如 50MB）
        'max-file-size' => env('FILE_MAX_SIZE', '50MB'),
        // 允许的文件扩展名白名单（置空表示不限制）
        'allowed-extensions' => ['jpg', 'jpeg', 'png', 'gif'],
    ],

    // MinIO 对象存储（S3 兼容）
    'minio' => [
        'endpoint'   => env('MINIO_ENDPOINT', 'http://111.229.83.153:9000'),
        'access-key' => env('MINIO_ACCESS_KEY', 'bybaddp7zyARpgNbEGKf'),
        'secret-key' => env('MINIO_SECRET_KEY', 'p9rBdQZPBIJcMH23iyFkZkXmmawbmwPlk3JLlaaj'),
        'bucket'     => env('MINIO_BUCKET', 'public'),
        // 自定义域名：配置后文件 URL 走域名，留空则用 endpoint（IP）格式
        'domain'     => env('MINIO_DOMAIN', ''),
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
