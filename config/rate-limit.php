<?php

// IP 全局限流配置（Redis ZSet 滑动窗口）
return [
    'ip' => [
        'enabled' => true,   // 是否启用 IP 全局限流（建议生产开启）
        'limit'   => 1000,   // 窗口内最大请求数
        'window'  => 60,     // 滑动窗口大小（秒）
    ],
];
