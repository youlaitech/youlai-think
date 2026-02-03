<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\LogService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="12.统计分析")
 */
final class StatisticsController extends ApiController
{
    /**
     * 访问趋势统计
     *
     * @OA\Get(
     *     path="/api/v1/statistics/visits/trend",
     *     summary="访问趋势统计",
     *     tags={"12.统计分析"},
     *     @OA\Parameter(name="startDate", in="query", description="开始时间", required=true, example="2024-01-01"),
     *     @OA\Parameter(name="endDate", in="query", description="结束时间", required=true, example="2024-12-31"),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function visitTrend(): \think\Response
    {
        $startDate = (string) $this->request->param('startDate', '');
        $endDate = (string) $this->request->param('endDate', '');
        $data = (new LogService())->getVisitTrend($startDate, $endDate);
        return $this->ok($data);
    }

    /**
     * 访问概览统计
     *
     * @OA\Get(
     *     path="/api/v1/statistics/visits/overview",
     *     summary="访问概览统计",
     *     tags={"12.统计分析"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function visitOverview(): \think\Response
    {
        $data = (new LogService())->getVisitStats();
        return $this->ok($data);
    }
}
