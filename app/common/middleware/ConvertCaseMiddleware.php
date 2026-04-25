<?php declare(strict_types=1);

namespace app\common\middleware;

use app\common\util\CaseConverter;
use think\Response;

/**
 * 请求/响应键名双向转换中间件
 */
final class ConvertCaseMiddleware
{
    public function handle($request, \Closure $next): Response
    {
        // 前置：请求参数 camelCase → snake_case
        $this->convertRequestParams($request);

        $response = $next($request);

        // 后置：响应数据 snake_case → camelCase
        return $this->convertResponseData($response);
    }

    /**
     * 请求参数 camelCase → snake_case
     */
    private function convertRequestParams($request): void
    {
        $params = CaseConverter::toSnakeCase((array) $request->param());

        // 合并 JSON body
        $content = (string) $request->getContent();
        if (!empty($content)) {
            $jsonBody = json_decode($content, true);
            if (is_array($jsonBody)) {
                $params = array_merge($params, CaseConverter::toSnakeCase($jsonBody));
            }
        }

        // 缓存到请求属性，供 ParamsTrait 读取
        $request->__snakeParams = $params;
    }

    /**
     * 响应数据 snake_case → camelCase
     */
    private function convertResponseData(Response $response): Response
    {
        if (method_exists($response, 'getData') && method_exists($response, 'data')) {
            $data = $response->getData();

            if (!is_array($data)) {
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $decoded = is_string($json) ? json_decode($json, true) : null;
                $data = is_array($decoded) ? $decoded : null;
            }

            if (is_array($data)) {
                $response->data(CaseConverter::toCamelCase($data));
            }

            return $response;
        }

        $content = $response->getContent();
        if (empty($content)) {
            return $response;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $response;
        }

        $converted = CaseConverter::toCamelCase($data);
        $response->content(json_encode($converted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }
}
