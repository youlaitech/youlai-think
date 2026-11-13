<?php declare(strict_types=1);

return [
    'app_namespace'    => '',
    'with_route'       => true,
    'default_app'      => 'index',
    'default_timezone' => 'Asia/Shanghai',

    // 以下三项仅在自动多应用模式下生效
    'app_map'          => [],
    'domain_bind'      => [],
    'deny_app_list'    => [],

    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',
    'exception_handle' => \app\ExceptionHandle::class,
    'error_message'    => '页面错误！请稍后再试～',
    'show_error_msg'   => env('APP_DEBUG', false),
];
