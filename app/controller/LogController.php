<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\LogService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="09.日志接口")
 */
final class LogController extends ApiController
{
    /**
     * 日志分页列表
     *
     * @OA\Get(
     *     path="/api/v1/logs",
     *     summary="日志分页列表",
     *     tags={"09.日志接口"},
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Parameter(name="keywords", in="query", description="关键字", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function page(): \think\Response
    {
        [$list, $total] = (new LogService())->getLogPage($this->request->param());
        return $this->okPage($list, $total);
    }
}
