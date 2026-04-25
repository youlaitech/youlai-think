<?php declare(strict_types=1);

namespace extend\redis;

use Predis\Client;

/**
 * Redis 客户端单例封装
 */
final class RedisClient
{
    private static ?Client $client = null;

    public static function get(): Client
    {
        if (self::$client === null) {
            self::$client = self::createClient();
        }
        return self::$client;
    }

    public static function reset(): void
    {
        self::$client = null;
    }

    private static function createClient(): Client
    {
        $cfg = config('security.redis') ?? [];
        return new Client([
            'scheme' => 'tcp',
            'host'   => $cfg['host'] ?? getenv('REDIS_HOST') ?: '127.0.0.1',
            'port'   => (int)($cfg['port'] ?? getenv('REDIS_PORT') ?: 6379),
            'database' => (int)($cfg['database'] ?? getenv('REDIS_DB') ?: 0),
            'password' => $cfg['password'] ?? getenv('REDIS_PASSWORD') ?: '',
        ], ['prefix' => $cfg['prefix'] ?? getenv('REDIS_PREFIX') ?: '']);
    }
}
