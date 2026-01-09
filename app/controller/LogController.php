<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\LogService;

/**
 * 日志接口 /api/v1/logs
 *
 * 日志分页查询
 */
final class LogController extends ApiController
{
    /**
     * 日志分页列表
     *
     * @return \think\Response
     */
    public function page(): \think\Response
    {
        [$list, $total] = (new LogService())->getLogPage($this->request->param());
        return $this->okPage($list, $total);
    }
}
