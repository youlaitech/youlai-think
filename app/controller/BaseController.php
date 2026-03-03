<?php declare(strict_types=1);

namespace app\controller;

use app\common\util\IdStringify;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\traits\AuthTrait;
use app\traits\PaginationTrait;
use app\traits\ParamsTrait;
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

    /**
     * 应用实例
     */
    protected App $app;

    /**
     * 请求实例
     */
    protected $request;

    /**
     * 构造函数 - 支持依赖注入
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;

        // 控制器初始化
        $this->initialize();
    }

    /**
     * 初始化方法 - 子类可重写
     */
    protected function initialize(): void
    {
    }

    // ==================== 响应方法 ====================

    /**
     * 成功响应
     */
    protected function success(mixed $data = null, string $message = ''): Json
    {
        $result = Result::success(
            $this->stringifyIds($data),
            $message ?: ResultCode::SUCCESS->getMsg()
        );

        return json($result->toArray(), 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 分页成功响应
     */
    protected function successPaginate(array $list, int $total, int $page = 1, int $pageSize = 10): Json
    {
        $result = Result::page(
            $this->stringifyIds($list),
            $total
        );

        return json($result->toArray(), 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    /**
     * 失败响应
     */
    protected function fail(string $code = '', string $message = ''): Json
    {
        $resultCode = $code ? ResultCode::fromCode($code) : ResultCode::SYSTEM_ERROR;
        $result = Result::failedWith(
            $resultCode,
            $message ?: $resultCode->getMsg()
        );

        return json($result->toArray(), 200, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
    }

    // ==================== 辅助方法 ====================

    /**
     * ID 字段转字符串（解决 JS 精度问题）
     */
    protected function stringifyIds(mixed $data): mixed
    {
        return IdStringify::stringify($data);
    }

    /**
     * 验证数据
     *
     * @throws ValidateException
     */
    protected function validate(array $data, string $validate, string $scene = ''): array
    {
        $validate = new $validate();

        if ($scene) {
            $validate->scene($scene);
        }

        return $validate->checkOrFail($data);
    }

    /**
     * 获取服务实例（简易依赖注入）
     *
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    protected function service(string $class): object
    {
        return $this->app->make($class);
    }
}
