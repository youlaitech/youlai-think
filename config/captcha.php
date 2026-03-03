<?php declare(strict_types=1);

return [
    // 验证码位数
    'length' => 4,

    // 验证码字符集（排除易混淆字符）
    'codeSet' => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',

    // 字体大小
    'fontSize' => 20,

    // 是否画混淆曲线
    'useCurve' => true,

    // 是否添加杂点
    'useNoise' => false,

    // 验证码图片高度
    'imageH' => 40,

    // 验证码图片宽度
    'imageW' => 130,

    // 验证码字体，不设置则随机
    'fontttf' => '',

    // 背景颜色
    'bg' => [255, 255, 255],

    // 过期时间（秒）
    'expire' => 300,
];
