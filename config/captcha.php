<?php declare(strict_types=1);

return [
    'length' => 4,
    // 排除易混淆字符
    'codeSet' => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
    'fontSize' => 20,
    'useCurve' => true,
    'useNoise' => false,
    'imageH' => 40,
    'imageW' => 130,
    // 为空则随机字体
    'fontttf' => '',
    'bg' => [255, 255, 255],
    // 秒
    'expire' => 300,
];
