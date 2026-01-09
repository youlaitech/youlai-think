<?php

declare(strict_types=1);

namespace app\common\util;

/**
 * ID 序列化工具
 *
 * 把响应数组里的 id / xxxId 转成字符串
 */
final class IdStringify
{
    /**
     * 将返回数据中的 ID 字段转为字符串
     *
     * @param mixed $data
     * @return mixed
     */
    public static function stringify(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        if (array_is_list($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = self::stringify($v);
            }
            return $out;
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out[$key] = self::stringify($value);
                continue;
            }

            if (self::isIdKey((string) $key) && (is_int($value) || is_float($value) || (is_string($value) && $value !== '' && is_numeric($value)))) {
                $out[$key] = (string) $value;
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * 是否为 ID/外键字段。
     */
    private static function isIdKey(string $key): bool
    {
        if ($key === 'id') {
            return true;
        }

        if ($key === 'value') {
            return true;
        }

        if ($key === 'createBy' || $key === 'updateBy') {
            return true;
        }

        if (str_ends_with($key, 'Id') || str_ends_with($key, '_id')) {
            return true;
        }

        return false;
    }
}
