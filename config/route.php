<?php declare(strict_types=1);

return [
    'pathinfo_depr'         => '/',
    'url_lazy_route'        => false,
    'url_route_must'        => true,
    'url_case_sensitive'    => false,
    'route_auto_group'      => false,
    'route_rule_merge'      => false,
    'route_complete_match'  => false,
    'remove_slash'          => false,
    'default_route_pattern' => '[\w\.]+',
    'url_html_suffix'       => '',
    'controller_layer'      => 'controller',
    'empty_controller'      => 'Error',
    'controller_suffix'     => false,
    // 开启自动多模块时生效
    'default_module'        => 'index',
    'default_controller'    => 'Index',
    'default_action'        => 'index',
    'action_suffix'         => '',
    'url_common_param'      => true,
    // route / get / param
    'action_bind_param'     => 'get',
    // true 为自动规则
    'request_cache_key'     => true,
    'request_cache_expire'  => null,
    'request_cache_except'  => [],
    'request_cache_tag'     => '',
    'api_version'           => 'Api-Version',
];
