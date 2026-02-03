<?php

namespace app\controller;

use app\BaseController;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="99.基础示例")
 */
class Index extends BaseController
{
    /**
     * @OA\Get(
     *     path="/",
     *     summary="欢迎页",
     *     tags={"99.基础示例"},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index()
    {
        return '<style>*{ padding: 0; margin: 0; }</style><iframe src="https://www.thinkphp.cn/welcome?version=' . \think\facade\App::version() . '" width="100%" height="100%" frameborder="0" scrolling="auto"></iframe>';
    }

    /**
     * @OA\Get(
     *     path="/hello/{name}",
     *     summary="Hello",
     *     tags={"99.基础示例"},
     *     @OA\Parameter(name="name", in="path", description="名称", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function hello($name = 'ThinkPHP8')
    {
        return 'hello,' . $name;
    }
}
