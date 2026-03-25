<?php declare(strict_types=1);

namespace app\system\service;

use app\common\exception\BusinessException;
use app\system\enums\LogModule;
use app\system\enums\ActionType;
use think\facade\Db;

/**
 * 日志与统计业务
 */
final class LogService
{
    public function getLogPage(array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $createTime = $queryParams['createTime'] ?? null;

        $q = Db::name('sys_log')->alias('l');

        if (is_array($createTime) && count($createTime) === 2) {
            $start = trim((string) ($createTime[0] ?? ''));
            $end = trim((string) ($createTime[1] ?? ''));
            if ($start !== '' && $end !== '') {
                $q = $q->whereBetweenTime('l.create_time', $start . ' 00:00:00', $end . ' 23:59:59');
            }
        }

        $total = (int) (clone $q)->count('l.id');

        $rows = $q
            ->field('l.id,l.module,l.action_type,l.title,l.content,l.operator_id,l.operator_name,l.status,l.request_uri,l.request_method,l.ip,l.province,l.city,l.device,l.browser,l.os,l.execution_time,l.error_msg,l.create_time')
            ->order('l.create_time', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $region = trim(($r['province'] ?? '') . ' ' . ($r['city'] ?? ''));

            // 转换枚举值为文本标签
            $moduleValue = (int) ($r['module'] ?? 0);
            $moduleLabel = '其他';
            if ($moduleValue > 0) {
                $moduleEnum = LogModule::tryFrom($moduleValue);
                if ($moduleEnum !== null) {
                    $moduleLabel = $moduleEnum->description();
                }
            }

            $actionTypeValue = (int) ($r['action_type'] ?? 0);
            $actionTypeLabel = '其他';
            if ($actionTypeValue > 0) {
                $actionTypeEnum = ActionType::tryFrom($actionTypeValue);
                if ($actionTypeEnum !== null) {
                    $actionTypeLabel = $actionTypeEnum->description();
                }
            }

            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'module' => $moduleLabel,
                'actionType' => $actionTypeLabel,
                'title' => (string) ($r['title'] ?? ''),
                'content' => (string) ($r['content'] ?? ''),
                'operatorId' => (string) ($r['operator_id'] ?? ''),
                'operatorName' => (string) ($r['operator_name'] ?? ''),
                'status' => (int) ($r['status'] ?? 0),
                'requestUri' => (string) ($r['request_uri'] ?? ''),
                'requestMethod' => (string) ($r['request_method'] ?? ''),
                'ip' => (string) ($r['ip'] ?? ''),
                'region' => $region ?: null,
                'device' => (string) ($r['device'] ?? ''),
                'browser' => (string) ($r['browser'] ?? ''),
                'os' => (string) ($r['os'] ?? ''),
                'executionTime' => isset($r['execution_time']) ? (int) $r['execution_time'] : null,
                'errorMsg' => (string) ($r['error_msg'] ?? ''),
                'createTime' => $r['create_time'] ?? null,
            ];
        }

        return [$list, $total];
    }

    public function getVisitTrend(string $startDate, string $endDate): array
    {
        $startDate = trim($startDate);
        $endDate = trim($endDate);
        if ($startDate === '' || $endDate === '') {
            throw new BusinessException('日期不能为空');
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs === false || $endTs === false) {
            throw new BusinessException('日期格式不正确');
        }

        if ($startTs > $endTs) {
            [$startTs, $endTs] = [$endTs, $startTs];
        }

        $dates = [];
        for ($ts = $startTs; $ts <= $endTs; $ts += 86400) {
            $dates[] = date('Y-m-d', $ts);
        }

        $start = $dates[0] . ' 00:00:00';
        $end = $dates[count($dates) - 1] . ' 23:59:59';

        $pvRows = Db::name('sys_log')
            ->whereBetweenTime('create_time', $start, $end)
            ->fieldRaw("COUNT(1) AS count, DATE_FORMAT(create_time,'%Y-%m-%d') AS date")
            ->group("DATE_FORMAT(create_time,'%Y-%m-%d')")
            ->select()
            ->toArray();

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

        $pvList = [];
        $ipList = [];
        foreach ($dates as $d) {
            $pvList[] = $pvMap[$d] ?? 0;
            $ipList[] = $ipMap[$d] ?? 0;
        }

        return [
            'dates' => $dates,
            'pvList' => $pvList,
            'ipList' => $ipList,
        ];
    }

    public function getVisitStats(): array
    {
        $today = date('Y-m-d');
        $nowTime = date('H:i:s');

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
