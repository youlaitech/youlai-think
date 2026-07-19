<?php
return [
    \app\middleware\RateLimitMiddleware::class,
    'alias' => [
        'auth' => \app\common\middleware\AuthMiddleware::class,
        'perm' => \app\common\middleware\PermMiddleware::class,
    ],
    'priority' => [
        \app\common\middleware\LogMiddleware::class,
        \app\common\middleware\AuthMiddleware::class,
    ],
];
