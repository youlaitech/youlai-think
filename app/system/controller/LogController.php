<?php declare(strict_types=1);

namespace app\system\controller;

use app\controller\BaseController;
use app\system\service\LogService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="09.日志接口")
 */
final class LogController extends BaseController
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
        [$list, $total] = $this->service(LogService::class)->getLogPage($this->getAllParams());
        return $this->success($list, $total);
    }

    /**
     * 访问趋势统计
     *
     * @OA\Get(
     *     path="/api/v1/logs/analytics/trend",
     *     summary="访问趋势统计",
     *     tags={"09.日志接口"},
     *     @OA\Parameter(name="startDate", in="query", description="开始日期", required=true),
     *     @OA\Parameter(name="endDate", in="query", description="结束日期", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function viewsTrend(): \think\response\Json
    {
        $startDate = $this->getParam('start_date', '');
        $endDate = $this->getParam('end_date', '');

        $data = $this->service(LogService::class)->getVisitTrend($startDate, $endDate);

        return $this->success($data);
    }

    /**
     * 访问统计概览
     *
     * @OA\Get(
     *     path="/api/v1/logs/analytics/overview",
     *     summary="访问统计概览",
     *     tags={"09.日志接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function views(): \think\response\Json
    {
        $data = $this->service(LogService::class)->getVisitStats();

        return $this->success($data);
    }
}
