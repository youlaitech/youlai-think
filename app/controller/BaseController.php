<?php declare(strict_types=1);

namespace app\controller;

use app\common\exception\BusinessException;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\common\traits\AuthTrait;
use app\common\traits\ParamsTrait;
use think\App;
use think\response\Json;

/**
 * 控制器基类
 */
abstract class BaseController
{
    use AuthTrait;
    use ParamsTrait;

    protected App $app;
    protected \think\Request $request;
    protected bool $requireAuth = false;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    protected function initialize(): void
    {
        if ($this->requireAuth && $this->getAuthUserId() <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }
    }

    protected function success(mixed $data = null, mixed $arg2 = null): Json
    {
        if (is_int($arg2)) {
            $result = Result::page($data, $arg2);
            return $this->jsonResponse($result);
        }

        $message = is_string($arg2) ? $arg2 : '';
        $result = Result::success(
            $data,
            $message ?: ResultCode::SUCCESS->getMsg()
        );

        return $this->jsonResponse($result);
    }

    protected function fail(string $code = '', string $message = ''): Json
    {
        $resultCode = $code ? ResultCode::fromCode($code) : ResultCode::SYSTEM_ERROR;
        $result = Result::failedWith(
            $resultCode,
            $message ?: $resultCode->getMsg()
        );

        return $this->jsonResponse($result);
    }

    private function jsonResponse(Result $result): Json
    {
        return json(
            $result->toArray(),
            200,
            [],
            ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
        );
    }

    protected function validate(array $data, string $validate, string $scene = ''): array
    {
        $validate = new $validate();

        if ($scene) {
            $validate->scene($scene);
        }

        return $validate->checkOrFail($data);
    }

    protected function service(string $class): object
    {
        return $this->app->make($class);
    }
}
