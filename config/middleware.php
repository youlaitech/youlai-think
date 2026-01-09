<?php
// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        'auth' => \app\middleware\AuthMiddleware::class,
        'perm' => \app\middleware\PermMiddleware::class,
        'dataScope' => \app\middleware\DataScopeMiddleware::class,
        'demo' => \app\middleware\DemoProtectMiddleware::class,
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [
        \app\middleware\AuthMiddleware::class,
        \app\middleware\DataScopeMiddleware::class,
    ],
];
