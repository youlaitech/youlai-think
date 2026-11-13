<?php declare(strict_types=1);

return [
    'alias' => [
        'auth' => \app\common\middleware\AuthMiddleware::class,
        'perm' => \app\common\middleware\PermMiddleware::class,
    ],
    'priority' => [
        \app\common\middleware\LogMiddleware::class,
        \app\common\middleware\AuthMiddleware::class,
    ],
];
