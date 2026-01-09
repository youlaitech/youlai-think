<?php
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
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }

        $resultCode = ResultCode::SYSTEM_ERROR;
        $msg = $e->getMessage();

        if ($e instanceof BusinessException) {
            $resultCode = $e->getResultCode();
            $msg = $e->getMessage();
        } elseif ($e instanceof ValidateException) {
            $resultCode = ResultCode::USER_REQUEST_PARAMETER_ERROR;
            $msg = $e->getError();
        } elseif ($e instanceof HttpException) {
            $statusCode = (int) $e->getStatusCode();
            $resultCode = match ($statusCode) {
                401 => ResultCode::ACCESS_UNAUTHORIZED,
                403 => ResultCode::ACCESS_PERMISSION_EXCEPTION,
                404 => ResultCode::INTERFACE_NOT_EXIST,
                default => ResultCode::SYSTEM_ERROR,
            };
        } elseif ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            $resultCode = ResultCode::INTERFACE_NOT_EXIST;
        }

        if (!config('app.show_error_msg')) {
            $msg = $resultCode->getMsg();
        }

        $payload = Result::failedWith($resultCode, $msg, IdStringify::stringify(null))->toArray();
        return json($payload);
    }
}
