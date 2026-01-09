<?php

declare(strict_types=1);

namespace app\common\redis;

final class RedisKey
{
    public static function format(string $pattern, mixed ...$args): string
    {
        $key = $pattern;

        foreach ($args as $arg) {
            $key = preg_replace('/\{\}/', (string) $arg, $key, 1) ?? $key;
        }

        return $key;
    }
}
