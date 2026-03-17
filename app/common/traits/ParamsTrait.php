<?php declare(strict_types=1);

namespace app\common\traits;

/**
 * 请求参数 Trait。
 * 统一处理请求参数获取，支持 JSON body 和 query params。
 */
trait ParamsTrait
{
    /**
     * 获取请求参数（兼容 JSON body）
     */
    protected function getParam(string $key, mixed $default = null): mixed
    {
        // 优先从路由参数获取
        $value = $this->request->param($key);
        if ($value !== null && $value !== '') {
            return $value;
        }

        // 从 JSON body 获取
        $json = $this->getJsonBody();
        if (is_array($json) && isset($json[$key])) {
            return $json[$key];
        }

        return $default;
    }

    /**
     * 获取所有请求参数
     */
    protected function getAllParams(): array
    {
        $params = $this->request->param();
        $json = $this->getJsonBody();

        if (is_array($json)) {
            $params = array_merge($json, $params);
        }

        $queryString = (string) $this->request->server('QUERY_STRING', '');
        if ($queryString !== '') {
            $matches = [];
            preg_match_all('/(?:^|&)createTime=([^&]*)/i', $queryString, $matches);
            if (!empty($matches[1])) {
                $values = array_values(array_filter(array_map('urldecode', $matches[1]), static fn ($v) => $v !== ''));
                if (count($values) === 1) {
                    $params['createTime'] ??= $values[0];
                } elseif (count($values) > 1) {
                    $params['createTime'] = $values;
                }
            }
        }

        return array_filter($params, fn ($v) => $v !== null && $v !== '');
    }

    protected function mergeJsonParams(): array
    {
        return $this->getAllParams();
    }

    /**
     * 获取 JSON body
     */
    protected function getJsonBody(): array
    {
        static $json = null;

        if ($json === null) {
            $content = $this->request->getContent();
            $json = json_decode($content, true) ?? [];
        }

        return $json;
    }

    /**
     * 获取路由 ID 参数
     */
    protected function getIdParam(): int
    {
        return (int) $this->request->param('id', 0);
    }

    /**
     * 获取批量 ID 参数
     */
    protected function getIdsParam(): array
    {
        $ids = $this->getParam('ids', '');
        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }

        return array_map('intval', $ids);
    }
}
