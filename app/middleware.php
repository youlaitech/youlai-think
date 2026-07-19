<?php
// 全局中间件
return [
    \app\common\middleware\ConvertCaseMiddleware::class,
    \app\common\middleware\LogMiddleware::class,
    \app\common\middleware\Cors::class,
];
