<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use think\App;

// [ 应用入口文件 ]

require __DIR__ . '/../vendor/autoload.php';

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        return true;
    }

    return false;
});

// 执行HTTP应用并响应
$http = (new App())->http;

// 忽略 PHP 8.5 废弃警告（第三方扩展尚未适配）
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$response = $http->run();

$response->send();

$http->end($response);
