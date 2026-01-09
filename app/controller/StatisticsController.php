<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\LogService;

/**
 * 访问统计接口 /api/v1/statistics
 *
 * 趋势统计 概览统计
 */
final class StatisticsController extends ApiController
{
    /**
     * 访问趋势统计
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
     * @return \think\Response
     */
    public function visitOverview(): \think\Response
    {
        $data = (new LogService())->getVisitStats();
        return $this->ok($data);
    }
}
