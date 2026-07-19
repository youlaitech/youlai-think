<?php declare(strict_types=1);

namespace app\common\middleware;

use app\common\util\CaseConverter;
use think\Response;

/**
 * 请求参数 camelCase→snake_case，响应数据 snake_case→camelCase
 */
final class ConvertCaseMiddleware
{
    public function handle($request, \Closure $next): Response
    {
        // Error::register() 在 App::initialize() 中设置了 error_reporting(E_ALL)，
        // 此处覆盖，排除 E_DEPRECATED，避免 ThinkPHP ORM 的 null 数组偏移废弃警告中断请求
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        $this->convertRequestParams($request);

        $response = $next($request);

        return $this->convertResponseData($response);
    }

    private function convertRequestParams($request): void
    {
        $params = CaseConverter::toSnakeCase((array) $request->param());

        $content = (string) $request->getContent();
        if ($content !== '') {
            $jsonBody = json_decode($content, true);
            if (is_array($jsonBody)) {
                $params = array_merge($params, CaseConverter::toSnakeCase($jsonBody));
            }
        }

        $request->snakeParams = $params;
    }

    private function convertResponseData(Response $response): Response
    {
        $data = $this->extractResponseData($response);

        if (!is_array($data)) {
            return $response;
        }

        $converted = CaseConverter::toCamelCase($data);

        if (method_exists($response, 'data')) {
            $response->data($converted);
        } else {
            $response->content(json_encode($converted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $response;
    }

    private function extractResponseData(Response $response): ?array
    {
        if (method_exists($response, 'getData')) {
            $data = $response->getData();
            if (is_array($data)) {
                return $data;
            }
            $decoded = json_decode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
            return is_array($decoded) ? $decoded : null;
        }

        $content = $response->getContent();
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
