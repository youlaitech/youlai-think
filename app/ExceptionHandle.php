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
use Throwable;

/**
 * 搴旂敤寮傚父澶勭悊绫?
 */
class ExceptionHandle extends Handle
{
    /**
     * 涓嶉渶瑕佽褰曚俊鎭紙鏃ュ織锛夌殑寮傚父绫诲垪琛?
     */
    protected array $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
        BusinessException::class,
    ];

    /**
     * 璁板綍寮傚父淇℃伅
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * 娓叉煋寮傚父涓篐TTP鍝嶅簲
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return parent::render($request, $e);
        }

        $resultCode = ResultCode::SYSTEM_ERROR;
        $msg = $e->getMessage();

        // 鏍规嵁寮傚父绫诲瀷鏄犲皠閿欒鐮?
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
            $msg = '鏁版嵁涓嶅瓨鍦?;
        }

        // 闈炶皟璇曟ā寮忛殣钘忚缁嗛敊璇俊鎭?
        if (!config('app.show_error_msg')) {
            $msg = $resultCode->getMsg();
        }

        // 鏋勫缓鍝嶅簲
        $result = Result::failedWith($resultCode, $msg);

        // 娣诲姞杩借釜ID锛堜究浜庢棩蹇楁帓鏌ワ級
        $traceId = $request->header('X-Request-Id') ?: $this->generateTraceId();
        $result->withTraceId($traceId);

        return json($result->toArray());
    }

    /**
     * 鐢熸垚杩借釜ID
     */
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
