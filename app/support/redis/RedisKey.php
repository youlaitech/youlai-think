<?php declare(strict_types=1);

namespace app\support\redis;

final class RedisKey
{
    public static function format(string $pattern, mixed ...$args): string
    {
        // 按顺序替�?{} 占位�?        $key = $pattern;

        foreach ($args as $arg) {
            $key = preg_replace('/\{\}/', (string) $arg, $key, 1) ?? $key;
        }

        return $key;
    }
}
