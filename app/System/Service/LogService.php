<?php declare(strict_types=1);

namespace app\system\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
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
            ->leftJoin('sys_user u', 'l.create_by = u.id')
            ->field('l.id,l.action_type,l.status,l.request_uri,l.request_method,l.ip,l.province,l.city,l.device,l.browser,l.os,l.execution_time,l.error_msg,l.create_by,l.create_time,u.nickname as operator')
            ->order('l.create_time', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $region = trim(($r['province'] ?? '') . ' ' . ($r['city'] ?? ''));

            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'actionType' => (string) ($r['action_type'] ?? ''),
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
                'createBy' => (string) ($r['create_by'] ?? ''),
                'operator' => (string) ($r['operator'] ?? ''),
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
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs === false || $endTs === false) {
            throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
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
            'uvList' => null,
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

    public function getUserEventPage(int $userId, array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $actionType = trim((string) ($queryParams['actionType'] ?? ''));
        $startDate = trim((string) ($queryParams['startDate'] ?? ''));
        $endDate = trim((string) ($queryParams['endDate'] ?? ''));

        $q = Db::name('sys_log')->where('create_by', $userId);

        if ($actionType !== '') {
            $q = $q->where('action_type', $actionType);
        }
        if ($startDate !== '') {
            $q = $q->where('create_time', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== '') {
            $q = $q->where('create_time', '<=', $endDate . ' 23:59:59');
        }

        $total = (int) (clone $q)->count('id');

        $rows = $q
            ->field('id,action_type,status,device,os,browser,ip,province,city,create_time')
            ->order('create_time', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $region = trim(($r['province'] ?? '') . ' ' . ($r['city'] ?? ''));
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'actionType' => (string) ($r['action_type'] ?? ''),
                'status' => (int) ($r['status'] ?? 0),
                'device' => (string) ($r['device'] ?? ''),
                'os' => (string) ($r['os'] ?? ''),
                'browser' => (string) ($r['browser'] ?? ''),
                'ip' => (string) ($r['ip'] ?? ''),
                'region' => $region ?: null,
                'createTime' => $r['create_time'] ?? null,
            ];
        }

        return [$list, $total];
    }

    public function getUserEventList(int $userId, array $queryParams, int $limit): array
    {
        $actionType = trim((string) ($queryParams['actionType'] ?? ''));
        $startDate = trim((string) ($queryParams['startDate'] ?? ''));
        $endDate = trim((string) ($queryParams['endDate'] ?? ''));

        $q = Db::name('sys_log')->where('create_by', $userId);

        if ($actionType !== '') {
            $q = $q->where('action_type', $actionType);
        }
        if ($startDate !== '') {
            $q = $q->where('create_time', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== '') {
            $q = $q->where('create_time', '<=', $endDate . ' 23:59:59');
        }

        $rows = $q
            ->field('id,action_type,status,device,os,browser,ip,province,city,create_time')
            ->order('create_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $region = trim(($r['province'] ?? '') . ' ' . ($r['city'] ?? ''));
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'actionType' => (string) ($r['action_type'] ?? ''),
                'status' => (int) ($r['status'] ?? 0),
                'device' => (string) ($r['device'] ?? ''),
                'os' => (string) ($r['os'] ?? ''),
                'browser' => (string) ($r['browser'] ?? ''),
                'ip' => (string) ($r['ip'] ?? ''),
                'region' => $region ?: null,
                'createTime' => $r['create_time'] ?? null,
            ];
        }

        return $list;
    }

    public function getLoginDevices(int $userId, int $days, int $limit): array
    {
        $startTime = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $rows = Db::name('sys_log')
            ->where('create_by', $userId)
            ->where('action_type', 'LOGIN')
            ->where('create_time', '>=', $startTime)
            ->field('device,os,browser,ip,province,city,COUNT(*) as login_count,MAX(create_time) as last_login_time')
            ->group('device,os,browser,ip')
            ->order('last_login_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $region = trim(($r['province'] ?? '') . ' ' . ($r['city'] ?? ''));
            $list[] = [
                'device' => (string) ($r['device'] ?? ''),
                'os' => (string) ($r['os'] ?? ''),
                'browser' => (string) ($r['browser'] ?? ''),
                'ip' => (string) ($r['ip'] ?? ''),
                'region' => $region ?: null,
                'loginCount' => (int) ($r['login_count'] ?? 0),
                'lastLoginTime' => $r['last_login_time'] ?? null,
            ];
        }

        return $list;
    }
}
