<?php declare(strict_types=1);

namespace app\common\traits;

/**
 * 请求参数读取
 */
trait ParamsTrait
{
    /**
     * 获取所有请求参数（已由 ConvertCaseMiddleware 转为 snake_case）
     *
     * snakeParams 在全局中间件阶段设置，此时路由尚未匹配，
     * 不含路由参数（id 等），需补充合并 $request->route()
     */
    protected function getAllParams(): array
    {
        $params = (array) ($this->request->snakeParams ?? $this->request->param());

        $route = $this->request->route();
        if (is_array($route) && $route) {
            $params = array_merge($params, $route);
        }

        return $params;
    }

    /**
     * 获取路由 ID 参数
     */
    protected function getIdParam(): int
    {
        return max(0, (int) $this->getParam('id', 0));
    }

    /**
     * 获取逗号分隔的 IDs 参数
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
     * 获取单个参数值
     */
    protected function getParam(string $key, mixed $default = null): mixed
    {
        $params = $this->getAllParams();

        if (array_key_exists($key, $params)) {
            $value = $params[$key];
            return $value !== '' && $value !== null ? $value : $default;
        }

        return $default;
    }

    /**
     * 获取所有参数（中间件已完成 URL 参数与 JSON body 的合并及 snake_case 转换）
     */
    protected function mergeJsonParams(): array
    {
        return $this->getAllParams();
    }

    /**
     * 过滤空值参数
     */
    protected function getFilteredParams(array $params): array
    {
        return array_filter($params, fn($v) => $v !== '' && $v !== null);
    }
}
