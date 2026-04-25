<?php declare(strict_types=1);

namespace app\common\util;

/**
 * camelCase / snake_case 双向转换
 */
final class CaseConverter
{
    /**
     * 递归将数组键从 camelCase 转为 snake_case
     */
    public static function toSnakeCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snakeKey = strtolower(preg_replace('/[A-Z]/', '_$0', (string) $key));
            $result[$snakeKey] = is_array($value) ? self::toSnakeCase($value) : $value;
        }
        return $result;
    }

    /**
     * 递归将数组键从 snake_case 转为 camelCase
     */
    public static function toCamelCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $camelKey = is_string($key) && str_contains($key, '_')
                ? preg_replace_callback('/_([a-zA-Z])/', static fn($m) => strtoupper($m[1]), $key)
                : $key;
            $result[$camelKey] = is_array($value) ? self::toCamelCase($value) : $value;
        }
        return $result;
    }
}
