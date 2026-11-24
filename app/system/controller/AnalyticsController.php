<?php declare(strict_types=1);

namespace app\system\controller;

use app\controller\BaseController;
use app\system\service\LogService;

/**
 * @OA\Tag(name="15.统计接口")
 */
final class AnalyticsController extends BaseController
{
    /**
     * 访问汇总
     */
    public function visitSummary(): \think\response\Json
    {
        return $this->success($this->service(LogService::class)->getVisitSummary());
    }

    /**
     * 访问趋势
     */
    public function visitTrend(): \think\response\Json
    {
        $startDate = (string) $this->getParam('start_date', '');
        $endDate = (string) $this->getParam('end_date', '');
        if ($startDate === '' || $endDate === '') {
            return $this->fail('B0001', '开始日期和结束日期不能为空');
        }
        if (strtotime($startDate) > strtotime($endDate)) {
            return $this->fail('B0001', '开始日期不能晚于结束日期');
        }
        if ((strtotime($endDate) - strtotime($startDate)) / 86400 >= 366) {
            return $this->fail('B0001', '查询跨度不能超过366天');
        }

        return $this->success($this->service(LogService::class)->getVisitTrend($startDate, $endDate));
    }

    /**
     * 用户汇总
     */
    public function userSummary(): \think\response\Json
    {
        return $this->success($this->service(LogService::class)->getUserSummary());
    }
}
