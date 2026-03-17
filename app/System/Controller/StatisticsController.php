<?php declare(strict_types=1);

namespace app\system\controller;

use app\BaseController;
use app\system\service\LogService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="12.统计分析")
 */
final class StatisticsController extends BaseController
{
    /**
     * 访问趋势统计
     */
    public function visitsTrend(): \think\response\Json
    {
        $startDate = $this->getParam('startDate', '');
        $endDate = $this->getParam('endDate', '');

        $data = $this->service(LogService::class)->getVisitTrend($startDate, $endDate);

        return $this->success($data);
    }

    /**
     * 访问概览统计
     */
    public function visitsOverview(): \think\response\Json
    {
        $data = $this->service(LogService::class)->getVisitStats();

        return $this->success($data);
    }
}
