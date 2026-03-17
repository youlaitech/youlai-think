<?php declare(strict_types=1);

namespace app;

use app\common\exception\BusinessException;
use app\common\util\IdStringify;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\common\traits\AuthTrait;
use app\common\traits\PaginationTrait;
use app\common\traits\ParamsTrait;
use think\App;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 控制器基类。
 * 提供通用的请求处理和响应方法。
 */
abstract class BaseController
{
    use AuthTrait;
    use PaginationTrait;
    use ParamsTrait;

    protected App $app;
    protected $request;
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

    protected function success(mixed $data = null, string $message = ''): Json
    {
        $result = Result::success(
            $this->stringifyIds($this->camelizeKeys($data)),
            $message ?: ResultCode::SUCCESS->getMsg()
        );

        return json($result->toArray(), 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    protected function successPaginate(array $list, int $total, int $page = 1, int $pageSize = 10): Json
    {
        $result = Result::page(
            $this->stringifyIds($this->camelizeKeys($list)),
            $total
        );

        return json($result->toArray(), 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    protected function fail(string $code = '', string $message = ''): Json
    {
        $resultCode = $code ? ResultCode::fromCode($code) : ResultCode::SYSTEM_ERROR;
        $result = Result::failedWith(
            $resultCode,
            $message ?: $resultCode->getMsg()
        );

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
