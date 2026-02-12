<?php

return [
    'session_mode' => env('SECURITY_SESSION_MODE', 'jwt'),

    'token_type' => 'Bearer',
    'token_header' => 'Authorization',
    'token_prefix' => 'Bearer ',

    'jwt' => [
        'secret' => env('JWT_SECRET', 'SecretKey012345678901234567890123456789012345678901234567890123456789'),
        'issuer' => env('JWT_ISSUER', 'youlai-think'),
        'access_ttl' => (int) env('JWT_ACCESS_TTL', 7200),
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 604800),
    ],

    'redis' => [
        'host' => env('REDIS_HOST', 'www.youlai.tech'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', '123456'),
        'database' => (int) env('REDIS_DB', 11),
        'prefix' => env('REDIS_PREFIX', ''),

        'access_ttl' => (int) env('REDIS_TOKEN_ACCESS_TTL', 7200),
        'refresh_ttl' => (int) env('REDIS_TOKEN_REFRESH_TTL', 604800),

        'keys' => [
            'access_token_user' => 'auth:token:access:{}',
            'refresh_token_user' => 'auth:token:refresh:{}',
            'user_access_token' => 'auth:user:access:{}',
            'user_refresh_token' => 'auth:user:refresh:{}',
            'blacklist_token' => 'auth:token:blacklist:{}',
            'user_token_version' => 'auth:user:token_version:{}',
        ],
    ],
];
