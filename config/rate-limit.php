<?php declare(strict_types=1);

// IP 全局限流（Redis ZSet 滑动窗口）
return [
    'ip' => [
        'enabled' => true,
        // 窗口内最大请求数
        'limit'   => 1000,
        // 秒
        'window'  => 60,
    ],
];
