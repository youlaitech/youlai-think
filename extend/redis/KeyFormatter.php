<?php declare(strict_types=1);

namespace extend\redis;

class KeyFormatter
{
    public static function format(string $pattern, int|string ...$params): string
    {
        if (empty($params)) {
            return $pattern;
        }
        return sprintf($pattern, ...$params);
    }
}
