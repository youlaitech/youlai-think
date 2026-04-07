<?php declare(strict_types=1);

namespace app\common\traits;

/**
 * 请求参数处理 Trait
 */
trait ParamsTrait
{
    /**
     * 获取所有请求参数（GET + POST）
     */
    protected function getAllParams(): array
    {
        return (array) $this->request->param();
    }

    /**
     * 获取路由中的 ID 参数
     */
    protected function getIdParam(): int
    {
        return max(0, (int) $this->request->param('id', 0));
    }

    /**
     * 获取路由中的 IDs 参数（逗号分隔）
     */
    protected function getIdsParam(): array
    {
        $ids = $this->request->param('ids', '');
        if (empty($ids)) {
            return [];
        }
        return array_values(array_filter(
            array_map('intval', explode(',', $ids)),
            fn($v) => $v > 0
        ));
    }

    /**
     * 获取 JSON 请求体
     */
    protected function getJsonBody(): ?array
    {
        $content = (string) $this->request->getContent();
        if (empty($content)) {
            return null;
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * 合并 URL 参数和 JSON 请求体
     */
    protected function mergeJsonParams(): array
    {
        $params = $this->getAllParams();
        $jsonBody = $this->getJsonBody();
        if (is_array($jsonBody)) {
            $params = array_merge($params, $jsonBody);
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
     * 获取带默认值的参数
     */
    protected function getParam(string $key, mixed $default = null): mixed
    {
        $value = request()->param($key, $default);
        return $value !== '' ? $value : $default;
    }
}
