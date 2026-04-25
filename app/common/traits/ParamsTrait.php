<?php declare(strict_types=1);

namespace app\common\traits;

use app\common\util\CaseConverter;

/**
 * 请求参数处理 Trait（兼容 camelCase / snake_case）
 */
trait ParamsTrait
{
    /**
     * 获取所有请求参数（已由中间件转为 snake_case）
     */
    protected function getAllParams(): array
    {
        return (array) ($this->request->__snakeParams ?? $this->request->param());
    }

    /**
     * 驼峰转下划线
     */
    private function camelToSnake(string $str): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
    }

    /**
     * 获取路由中的 ID 参数
     */
    protected function getIdParam(): int
    {
        return max(0, (int) $this->getParam('id', 0));
    }

    /**
     * 获取路由中的 IDs 参数（逗号分隔）
     */
    protected function getIdsParam(): array
    {
        $ids = $this->getParam('ids', '');
        if (empty($ids)) {
            return [];
        }
        return array_values(array_filter(
            array_map('intval', explode(',', $ids)),
            fn($v) => $v > 0
        ));
    }

    /**
     * 合并 URL 参数与 JSON body，同时保留两种命名风格
     */
    protected function mergeJsonParams(): array
    {
        $params = $this->getAllParams();

        $content = (string) $this->request->getContent();
        if ($content !== '') {
            $json = json_decode($content, true);
            if (is_array($json)) {
                $params = array_merge($params, CaseConverter::toSnakeCase($json), $json);
            }
        }

        return $params;
    }

    /**
     * 获取过滤空值的参数数组
     */
    protected function getFilteredParams(array $params): array
    {
        return array_filter($params, fn($v) => $v !== '' && $v !== null);
    }

    /**
     * 获取参数值，优先读 snake_case，也兼容 camelCase
     */
    protected function getParam(string $key, mixed $default = null): mixed
    {
        $params = $this->getAllParams();

        $snakeKey = preg_match('/[A-Z]/', $key) ? $this->camelToSnake($key) : $key;
        $camelKey = str_contains($key, '_')
            ? str_replace('_', '', lcfirst(ucwords($key, '_')))
            : $key;
        $altCamelKey = str_contains($snakeKey, '_')
            ? str_replace('_', '', lcfirst(ucwords($snakeKey, '_')))
            : $camelKey;

        // 优先读取 snake_case 参数（中间件转换后的）
        if (array_key_exists($key, $params)) {
            $value = $params[$key];
            return $value !== '' && $value !== null ? $value : $default;
        }
        if ($snakeKey !== $key && array_key_exists($snakeKey, $params)) {
            $value = $params[$snakeKey];
            return $value !== '' && $value !== null ? $value : $default;
        }

        // 如果没找到，尝试读取 camelCase 参数（前端直接传的原始命名）
        // 把 snake_case key 转成 camelCase 再试
        if (array_key_exists($camelKey, $params)) {
            $value = $params[$camelKey];
            return $value !== '' && $value !== null ? $value : $default;
        }
        if ($altCamelKey !== $camelKey && array_key_exists($altCamelKey, $params)) {
            $value = $params[$altCamelKey];
            return $value !== '' && $value !== null ? $value : $default;
        }

        // 再试试原始 param 里是否有（包含 query/form/json）
        $originalParams = $this->request->param();
        if (array_key_exists($key, $originalParams)) {
            $value = $originalParams[$key];
            return $value !== '' && $value !== null ? $value : $default;
        }
        if ($snakeKey !== $key && array_key_exists($snakeKey, $originalParams)) {
            $value = $originalParams[$snakeKey];
            return $value !== '' && $value !== null ? $value : $default;
        }
        if (array_key_exists($camelKey, $originalParams)) {
            $value = $originalParams[$camelKey];
            return $value !== '' && $value !== null ? $value : $default;
        }
        if ($altCamelKey !== $camelKey && array_key_exists($altCamelKey, $originalParams)) {
            $value = $originalParams[$altCamelKey];
            return $value !== '' && $value !== null ? $value : $default;
        }

        return $default;
    }
}
