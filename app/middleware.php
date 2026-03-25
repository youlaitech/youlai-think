<?php
// 应用中间件定义文件
return [
    'auth'     => \app\common\middleware\AuthMiddleware::class,
    'perm'     => \app\common\middleware\PermMiddleware::class,
    'dataScope' => \app\common\middleware\DataScopeMiddleware::class,
];
