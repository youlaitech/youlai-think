<?php declare(strict_types=1);

namespace app\controller;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;

/**
 * API 控制器基�? *
 * 专门用于 API 接口，包含认证检�? */
abstract class ApiController extends BaseController
{
    protected function ok(mixed $data = null): \think\Response
    {
        return $this->success($data);
    }

    protected function okPage(array $list, int $total): \think\Response
    {
        $pagination = $this->getPaginationParams();

        return $this->successPaginate(
            $list,
            $total,
            $pagination['page'],
            $pagination['pageSize']
        );
    }

    /**
     * 初始�?- 检查认�?     */
    protected function initialize(): void
    {
        // 检查是否已登录
        if ($this->getAuthUserId() <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }
    }

    /**
     * 演示模式检�?     */
    protected function checkDemo(): void
    {
        if (env('IS_DEMO', false)) {
            throw new BusinessException(
                ResultCode::SYSTEM_ERROR,
                '演示环境禁止操作'
            );
        }
    }
}
