<?php declare(strict_types=1);

namespace app;

use app\common\exception\BusinessException;
use app\common\util\IdStringify;
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
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
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
     * 记录异常信息
     */
    public function report(Throwable $exception): void
    {
        // 业务异常不需要记录详细日志
        if ($exception instanceof BusinessException) {
            return;
        }

        // 记录详细异常日志
        $request = request();
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile() . ':' . $exception->getLine(),
            'url' => $request ? $request->url() : 'unknown',
            'method' => $request ? $request->method() : 'unknown',
            'params' => $request ? $request->param() : [],
            'ip' => $request ? $request->ip() : 'unknown',
            'user_id' => $request ? $request->user_id ?? null : null,
            'trace' => array_slice($exception->getTrace(), 0, 5),
        ];

        Log::error('系统异常', $context);
    }

    /**
     * 渲染异常为 HTTP 响应
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }

        $resultCode = ResultCode::SYSTEM_ERROR;
        $msg = $e->getMessage();

        // 根据异常类型映射错误码
        if ($e instanceof BusinessException) {
            $resultCode = $e->getResultCode();
        } elseif ($e instanceof ValidateException) {
            $resultCode = ResultCode::USER_REQUEST_PARAMETER_ERROR;
            $msg = $e->getError();
        } elseif ($e instanceof HttpException) {
            $resultCode = match ((int) $e->getStatusCode()) {
                401 => ResultCode::ACCESS_UNAUTHORIZED,
                403 => ResultCode::ACCESS_PERMISSION_EXCEPTION,
                404 => ResultCode::INTERFACE_NOT_EXIST,
                default => ResultCode::SYSTEM_ERROR,
            };
        } elseif ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            $resultCode = ResultCode::INTERFACE_NOT_EXIST;
            $msg = '数据不存在';
        }

        // 非调试模式隐藏详细错误信息
        if (!config('app.show_error_msg')) {
            $msg = $resultCode->getMsg();
        }

        // 构建响应
        $result = Result::failedWith($resultCode, $msg);

        // 添加追踪ID（便于日志排查）
        $traceId = $request->header('X-Request-Id') ?: $this->generateTraceId();
        $result->withTraceId($traceId);

        // 根据业务错误码映射 HTTP 状态码
        $httpStatus = $this->mapHttpStatus($resultCode);

        return json($result->toArray())->code($httpStatus);
    }

    /**
     * 根据业务结果码映射 HTTP 状态码
     */
    private function mapHttpStatus(ResultCode $resultCode): int
    {
        return match ($resultCode) {
            ResultCode::ACCESS_UNAUTHORIZED,
            ResultCode::ACCESS_TOKEN_INVALID,
            ResultCode::REFRESH_TOKEN_INVALID => 401,
            ResultCode::ACCESS_PERMISSION_EXCEPTION => 403,
            ResultCode::INTERFACE_NOT_EXIST => 404,
            default => 400,
        };
    }

    /**
     * 生成追踪ID
     */
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
