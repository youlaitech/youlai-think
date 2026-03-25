<?php declare(strict_types=1);

namespace app\common\util;

/**
 * ID 字符串化工具类
 * 将整数 ID 转换为字符串，防止前端精度丢失
 */
final class IdStringify
{
    /**
     * 递归将数据中的整数 ID 字段转换为字符串
     *
     * @param mixed $data
     * @return mixed
     */
    public static function stringify(mixed $data): mixed
    {
        if ($data === null) {
            return null;
        }

        if (is_array($data)) {
            return self::processArray($data);
        }

        if (is_object($data)) {
            return self::processArray((array) $data);
        }

        return $data;
    }

    /**
     * 处理数组，递归转换 ID 字段
     */
    private static function processArray(array $data): array
    {
        $result = [];
        $idFields = ['id', 'userId', 'parentId', 'deptId', 'roleId', 'menuId', 'createBy', 'updateBy', 'operatorId'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::processArray($value);
            } elseif (in_array($key, $idFields, true) && is_int($value)) {
                $result[$key] = (string) $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
