<?php

declare(strict_types=1);

namespace app\common\redis;

use Predis\Client;

final class RedisClient
{
    private static ?Client $client = null;

    public static function get(): Client
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $cfg = config('security.redis');

        // 仅在首次调用时初始化连接

        $parameters = [
            'scheme' => 'tcp',
            'host' => $cfg['host'] ?? '127.0.0.1',
            'port' => $cfg['port'] ?? 6379,
            'database' => $cfg['database'] ?? 0,
        ];

        if (!empty($cfg['password'])) {
            $parameters['password'] = $cfg['password'];
        }

        self::$client = new Client($parameters, [
            'prefix' => $cfg['prefix'] ?? '',
        ]);

        return self::$client;
    }
}
