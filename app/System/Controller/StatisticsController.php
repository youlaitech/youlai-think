<?php declare(strict_types=1);

namespace app\System\Controller;

use app\controller\ApiController;
use app\System\Service\LogService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="12.统计分析")
 */
final class StatisticsController extends ApiController
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
