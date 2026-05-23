<?php declare(strict_types=1);

namespace app;

use app\common\exception\BusinessException;
use app\common\util\CaseConverter;
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
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    public function report(Throwable $e): void
    {
        $ctx = $this->requestContext();

        if ($e instanceof BusinessException) {
            Log::debug(sprintf('[business] %s %s', get_class($e), $e->getMessage()), $ctx);
            return;
        }

        // 系统异常：完整堆栈
        $log = sprintf(
            "[%s] %s in %s:%d\nRequest: %s %s | IP: %s\nStack:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $ctx['method'] ?? '?',
            $ctx['url'] ?? '?',
            $ctx['ip'] ?? '?',
            $e->getTraceAsString(),
        );

        Log::error($log);
        error_log($log);
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }

        if ($e instanceof BusinessException) {
            return $this->fail($e->getResultCode(), $e->getMessage() ?: null);
        }

        if ($e instanceof ValidateException) {
            return $this->fail(ResultCode::USER_REQUEST_PARAMETER_ERROR, $e->getError());
        }

        if ($e instanceof HttpException && $e->getStatusCode() === 404) {
            return $this->fail(ResultCode::INTERFACE_NOT_EXIST, '', 404);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return $this->fail(ResultCode::INTERFACE_NOT_EXIST, '数据不存在');
        }

        if ($e instanceof HttpException) {
            $resultCode = match ((int) $e->getStatusCode()) {
                401 => ResultCode::ACCESS_TOKEN_INVALID,
                403 => ResultCode::ACCESS_PERMISSION_EXCEPTION,
                default => ResultCode::SYSTEM_ERROR,
            };
            return $this->fail($resultCode);
        }

        // 兜底系统异常
        $msg = $this->app->isDebug() ? $e->getMessage() : ResultCode::SYSTEM_ERROR->getMsg();
        return $this->fail(ResultCode::SYSTEM_ERROR, $msg, 500);
    }

    /**
     * 失败响应
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
            CaseConverter::toCamelCase($result->toArray()),
            $httpStatus,
            [],
            ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
        );
    }

    /**
     * 收集请求上下文
     */
    private function requestContext(): array
    {
        try {
            $r = request();
            return [
                'url'    => $r ? $r->url(true) : 'unknown',
                'method' => $r ? $r->method() : '?',
                'ip'     => $r ? $r->ip() : '?',
            ];
        } catch (Throwable) {
            return ['url' => 'unknown', 'method' => '?', 'ip' => '?'];
        }
    }
}
