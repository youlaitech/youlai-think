<?php declare(strict_types=1);

namespace app\controller;

use app\common\exception\BusinessException;
use app\common\util\IdStringify;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\common\traits\AuthTrait;
use app\common\traits\ParamsTrait;
use think\App;
use think\response\Json;

/**
 * 封装 success/fail 响应方法、参数读取、分页、ID转换等公共能力
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

    /**
     * 成功响应（自动判断普通/分页）
     * - success($data) 普通响应
     * - success($list, $total) 分页响应
     * - success($data, 'message') 带消息响应
     */
    protected function success(mixed $data = null, mixed $arg2 = null): Json
    {
        // 第二个参数是整数 → 分页响应
        if (is_int($arg2)) {
            $result = Result::page(
                $this->stringifyIds($this->camelizeKeys($data)),
                $arg2
            );
            return $this->jsonResponse($result);
        }

        // 第二个参数是字符串 → 带消息响应
        $message = is_string($arg2) ? $arg2 : '';
        $result = Result::success(
            $this->stringifyIds($this->camelizeKeys($data)),
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

    /**
     * 统一 JSON 响应（保持编码格式一致）
     */
    private function jsonResponse(Result $result): Json
    {
        return json($result->toArray(), 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    protected function stringifyIds(mixed $data): mixed
    {
        return IdStringify::stringify($data);
    }

    protected function camelizeKeys(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        if (array_is_list($data)) {
            return array_map(fn ($v) => $this->camelizeKeys($v), $data);
        }

        $out = [];
        foreach ($data as $key => $value) {
            $newKey = $key;
            if (is_string($key) && str_contains($key, '_')) {
                $newKey = preg_replace_callback('/_([a-zA-Z])/', static fn ($m) => strtoupper($m[1]), $key);
            }
            $out[$newKey] = $this->camelizeKeys($value);
        }

        return $out;
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
