<?php

declare(strict_types=1);

namespace app\controller;

use OpenApi\Annotations as OA;
use OpenApi\Generator;
use think\facade\App;

/**
 * @OA\Info(
 *     title="youlai-think",
 *     version="1.0",
 *     description="youlai 全家桶（ThinkPHP 8）权限管理后台接口文档"
 * )
 */
final class SwaggerController
{
    /**
     * OpenAPI JSON
     */
    public function openapi(): \think\Response
    {
        if (isset($_GET['debug'])) {
            $debug = [
                'ini_loaded_file' => php_ini_loaded_file(),
                'opcache.save_comments' => ini_get('opcache.save_comments'),
                'opcache.load_comments' => ini_get('opcache.load_comments'),
                'opcache.enable' => ini_get('opcache.enable'),
                'app_path' => App::getAppPath(),
                'controller_path' => App::getRootPath() . 'app/controller',
                'swagger_doc_comment' => (new \ReflectionClass(self::class))->getDocComment(),
            ];

            return response(json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200)->header([
                'Content-Type' => 'application/json; charset=utf-8',
            ]);
        }

        $scanPaths = [
            App::getAppPath(),
        ];

        $openapi = Generator::scan($scanPaths, ['validate' => false]);

        return response($openapi->toJson(), 200)->header([
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    /**
     * Swagger UI
     */
    public function ui(): \think\Response
    {
        $html = <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <title>youlai-think</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
        body { margin: 0; background: #f7f7f7; }
        #swagger-ui { max-width: 1200px; margin: 0 auto; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
    window.onload = function () {
        window.ui = SwaggerUIBundle({
            url: '/swagger/openapi',
            dom_id: '#swagger-ui',
            presets: [SwaggerUIBundle.presets.apis],
            layout: 'BaseLayout',
            tagsSorter: 'alpha'
        });
    };
</script>
</body>
</html>
HTML;

        return response($html, 200)->header([
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}
