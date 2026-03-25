<?php
// 中间件配置
return [
    // 全局中间件（按顺序执行）
    'global' => [
        \app\common\middleware\RateLimitMiddleware::class,
        \app\common\middleware\LogMiddleware::class,
        \app\common\middleware\Cors::class,
    ],
    // 别名或分组
    'alias' => [
        'auth' => \app\common\middleware\AuthMiddleware::class,
        'perm' => \app\common\middleware\PermMiddleware::class,
        'dataScope' => \app\common\middleware\DataScopeMiddleware::class,
    ],
    // 优先级设置
    'priority' => [
        \app\common\middleware\RateLimitMiddleware::class,
        \app\common\middleware\LogMiddleware::class,
        \app\common\middleware\AuthMiddleware::class,
        \app\common\middleware\DataScopeMiddleware::class,
    ],
];
