<?php

// +----------------------------------------------------------------------
// | 数据库迁移配置
// +----------------------------------------------------------------------

return [
    // 迁移文件存放目录
    'path' => app_path() . 'database/migrations',

    // 数据库种子文件目录
    'seeds_path' => app_path() . 'database/seeds',

    // 迁移表名
    'migration_table' => 'migrations',

    // 默认数据库连接
    'connection' => env('DB_DRIVER', 'mysql'),
];
