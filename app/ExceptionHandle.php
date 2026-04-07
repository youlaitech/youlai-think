<?php declare(strict_types=1);

namespace app\common\exception;

use app\common\web\Result;
use app\common\web\ResultCode;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use think\facade\Log;
use Throwable;

/**
 * 全局异常处理器
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录日志的异常类
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
        BusinessException::class,
    ];

    /**
     * 记录异常日志
     */
    public function report(Throwable $exception): void
    {
        if ($exception instanceof BusinessException) {
            return;
        }

        $request = request();
        Log::error('系统异常', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile() . ':' . $exception->getLine(),
            'url' => $request ? $request->url() : 'unknown',
            'method' => $request ? $request->method() : 'unknown',
            'ip' => $request ? $request->ip() : 'unknown',
        ]);
    }

    /**
     * 渲染异常响应
     * 统一返回 HTTP 200 + Result JSON，通过 code 区分业务状态
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }

        // 业务异常：返回具体错误信息
        if ($e instanceof BusinessException) {
            $resultCode = $e->getResultCode();
            $msg = $e->getMessage() ?: $resultCode->getMsg();
            return $this->fail($resultCode, $msg);
        }

        // 参数校验异常
        if ($e instanceof ValidateException) {
            return $this->fail(ResultCode::USER_REQUEST_PARAMETER_ERROR, $e->getError());
        }

        // 路由不存在
        if ($e instanceof HttpException && $e->getStatusCode() === 404) {
            return $this->fail(ResultCode::INTERFACE_NOT_EXIST, '', 404);
        }

        // 数据不存在
        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return $this->fail(ResultCode::INTERFACE_NOT_EXIST, '数据不存在');
        }

        // 其他 HTTP 异常
        if ($e instanceof HttpException) {
            $resultCode = match ((int) $e->getStatusCode()) {
                401 => ResultCode::ACCESS_TOKEN_INVALID,
                403 => ResultCode::ACCESS_PERMISSION_EXCEPTION,
                default => ResultCode::SYSTEM_ERROR,
            };
            return $this->fail($resultCode);
        }

        // 系统异常：开发模式显示详情，生产模式隐藏
        $isDebug = config('app.show_error_msg', false);
        $msg = $isDebug ? $e->getMessage() : ResultCode::SYSTEM_ERROR->getMsg();

        return $this->fail(ResultCode::SYSTEM_ERROR, $msg, 500);
    }

    /**
     * 返回失败响应
     * 401: 未认证（token无效/过期）
     * 403: 权限不足
     * 404: 资源不存在
     * 500: 服务器错误
     * 200: 其他业务错误
     */
    private function fail(ResultCode $resultCode, string $msg = '', ?int $httpStatus = null): Response
    {
        $result = Result::failedWith($resultCode, $msg ?: $resultCode->getMsg());

        if ($httpStatus === null) {
            $httpStatus = match ($resultCode) {
                ResultCode::ACCESS_UNAUTHORIZED,
                ResultCode::ACCESS_TOKEN_INVALID,
                ResultCode::REFRESH_TOKEN_INVALID => 401,
                ResultCode::ACCESS_PERMISSION_EXCEPTION => 403,
                default => 200,
            };
        }

        return json(
            $result->toArray(),
            $httpStatus,
            [],
            ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
        );
    }
}
