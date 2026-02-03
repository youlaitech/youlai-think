<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use think\facade\Db;

/**
 * 日志与统计业务
 *
 * 日志分页 趋势统计 概览统计
 */
final class LogService
{
    /**
     * 日志分页列表。
     */
    public function getLogPage(array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $keywords = trim((string) ($queryParams['keywords'] ?? ''));
        $createTime = $queryParams['createTime'] ?? null;

        $q = Db::name('sys_log')->alias('l');

        // sys_log 无 is_deleted 字段，按 create_time 过滤即可
        if ($keywords !== '') {
            $kw = '%' . $keywords . '%';
            $q = $q->whereLike('l.content|l.request_uri|l.request_method|l.province|l.city|l.browser|l.os', $kw);
        }

        // 仅支持时间范围查询
        if (is_array($createTime) && count($createTime) === 2) {
            $start = trim((string) ($createTime[0] ?? ''));
            $end = trim((string) ($createTime[1] ?? ''));
            if ($start !== '' && $end !== '') {
                // 前端传 YYYY-MM-DD，后端补全时分秒
                $q = $q->whereBetweenTime('l.create_time', $start . ' 00:00:00', $end . ' 23:59:59');
            }
        }

        $total = (int) (clone $q)->count('l.id');

        $rows = $q
            ->leftJoin('sys_user u', 'l.create_by = u.id')
            ->field('l.id,l.module,l.content,l.request_uri,l.request_method,l.ip,l.province,l.city,l.browser,l.os,l.execution_time,l.create_time,u.nickname as operator')
            ->order('l.create_time', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $region = '';
            $province = trim((string) ($r['province'] ?? ''));
            $city = trim((string) ($r['city'] ?? ''));
            if ($province !== '' && $city !== '') {
                $region = $province . '/' . $city;
            } else {
                $region = $province !== '' ? $province : $city;
            }

            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'module' => (string) ($r['module'] ?? ''),
                'content' => (string) ($r['content'] ?? ''),
                'requestUri' => (string) ($r['request_uri'] ?? ''),
                'method' => (string) ($r['request_method'] ?? ''),
                'ip' => (string) ($r['ip'] ?? ''),
                'region' => $region,
                'browser' => (string) ($r['browser'] ?? ''),
                'os' => (string) ($r['os'] ?? ''),
                'executionTime' => isset($r['execution_time']) ? (int) $r['execution_time'] : 0,
                'operator' => (string) ($r['operator'] ?? ''),
                'createTime' => $r['create_time'] ?? null,
            ];
        }

        return [$list, $total];
    }

    /**
     * 获取访问趋势。
     */
    public function getVisitTrend(string $startDate, string $endDate): array
    {
        $startDate = trim($startDate);
        $endDate = trim($endDate);
        if ($startDate === '' || $endDate === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        // 归一化日期范围
        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs === false || $endTs === false) {
            throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
        }

        if ($startTs > $endTs) {
            [$startTs, $endTs] = [$endTs, $startTs];
        }

        // 生成连续日期坐标
        $dates = [];
        for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
            $dates[] = date('Y-m-d', $ts);
        }

        $start = $dates[0] . ' 00:00:00';
        $end = $dates[count($dates) - 1] . ' 23:59:59';

        // PV 统计
        $pvRows = Db::name('sys_log')
            ->whereBetweenTime('create_time', $start, $end)
            ->fieldRaw("COUNT(1) AS count, DATE_FORMAT(create_time,'%Y-%m-%d') AS date")
            ->group("DATE_FORMAT(create_time,'%Y-%m-%d')")
            ->select()
            ->toArray();

        // IP 去重统计
        $ipRows = Db::name('sys_log')
            ->whereBetweenTime('create_time', $start, $end)
            ->fieldRaw("COUNT(DISTINCT ip) AS count, DATE_FORMAT(create_time,'%Y-%m-%d') AS date")
            ->group("DATE_FORMAT(create_time,'%Y-%m-%d')")
            ->select()
            ->toArray();

        $pvMap = [];
        foreach ($pvRows as $r) {
            $pvMap[(string) ($r['date'] ?? '')] = (int) ($r['count'] ?? 0);
        }

        $ipMap = [];
        foreach ($ipRows as $r) {
            $ipMap[(string) ($r['date'] ?? '')] = (int) ($r['count'] ?? 0);
        }

        // 补齐没有日志的日期
        $pvList = [];
        $ipList = [];
        foreach ($dates as $d) {
            $pvList[] = $pvMap[$d] ?? 0;
            $ipList[] = $ipMap[$d] ?? 0;
        }

        return [
            'dates' => $dates,
            'pvList' => $pvList,
            'uvList' => null,
            'ipList' => $ipList,
        ];
    }

    /**
     * 获取访问概览。
     */
    public function getVisitStats(): array
    {
        $today = date('Y-m-d');
        $nowTime = date('H:i:s');

        // 访问量与访客数概览
        $pvTotal = (int) Db::name('sys_log')->count('id');
        $pvToday = (int) Db::name('sys_log')->whereBetweenTime('create_time', $today . ' 00:00:00', $today . ' 23:59:59')->count('id');

        $pvYesterdayTillNow = (int) Db::name('sys_log')
            ->whereBetweenTime('create_time', date('Y-m-d', strtotime('-1 day')) . ' 00:00:00', date('Y-m-d', strtotime('-1 day')) . ' ' . $nowTime)
            ->count('id');

        $pvGrowth = 0.0;
        if ($pvYesterdayTillNow > 0) {
            $pvGrowth = round(($pvToday - $pvYesterdayTillNow) / $pvYesterdayTillNow, 2);
        }

        $uvTotal = (int) Db::name('sys_log')->distinct(true)->count('ip');
        $uvToday = (int) Db::name('sys_log')->whereBetweenTime('create_time', $today . ' 00:00:00', $today . ' 23:59:59')->distinct(true)->count('ip');

        $uvYesterdayTillNow = (int) Db::name('sys_log')
            ->whereBetweenTime('create_time', date('Y-m-d', strtotime('-1 day')) . ' 00:00:00', date('Y-m-d', strtotime('-1 day')) . ' ' . $nowTime)
            ->distinct(true)
            ->count('ip');

        $uvGrowth = 0.0;
        if ($uvYesterdayTillNow > 0) {
            $uvGrowth = round(($uvToday - $uvYesterdayTillNow) / $uvYesterdayTillNow, 2);
        }

        return [
            'todayUvCount' => $uvToday,
            'totalUvCount' => $uvTotal,
            'uvGrowthRate' => $uvGrowth,
            'todayPvCount' => $pvToday,
            'totalPvCount' => $pvTotal,
            'pvGrowthRate' => $pvGrowth,
        ];
    }
}
