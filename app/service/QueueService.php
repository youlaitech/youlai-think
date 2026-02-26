<?php declare(strict_types=1);

namespace app\service;

use think\facade\Queue;

/**
 * 队列服务�?
 * 封装队列操作，提供简洁的任务分发接口�?
 */
final class QueueService
{
    /**
     * 推送任务到默认队列�?
     * @param string $job 任务类名
     * @param array $data 任务数据
     * @param int $delay 延迟秒数�?表示立即执行�?
     * @return string|null 任务ID
     */
    public static function push(string $job, array $data = [], int $delay = 0): ?string
    {
        if ($delay > 0) {
            return Queue::later($delay, $job, $data);
        }
        return Queue::push($job, $data);
    }

    /**
     * 推送任务到指定队列�?
     * @param string $queue 队列名称
     * @param string $job 任务类名
     * @param array $data 任务数据
     * @param int $delay 延迟秒数
     * @return string|null
     */
    public static function pushOn(string $queue, string $job, array $data = [], int $delay = 0): ?string
    {
        if ($delay > 0) {
            return Queue::laterOn($queue, $delay, $job, $data);
        }
        return Queue::pushOn($queue, $job, $data);
    }

    /**
     * 导出Excel任务�?
     * @param string $job 具体导出任务�?
     * @param array $params 导出参数
     * @param string $notifyUrl 完成通知URL
     * @return string|null
     */
    public static function exportExcel(string $job, array $params, string $notifyUrl = ''): ?string
    {
        $data = [
            'params' => $params,
            'notify_url' => $notifyUrl,
        ];
        return self::pushOn('export', $job, $data);
    }
}
