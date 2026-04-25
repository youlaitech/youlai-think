<?php declare(strict_types=1);

namespace app\controller;

use app\common\exception\BusinessException;
use app\common\util\CaseConverter;
use app\common\util\IdStringify;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\common\traits\AuthTrait;
use app\common\traits\ParamsTrait;
use think\App;
use think\response\Json;

/**
 * 控制器基类，提供通用响应、参数读取和分页能力
 */
abstract class BaseController
{
    use AuthTrait;
    use ParamsTrait;

    /**
     * 应用实例
     */
    protected App $app;

    /**
     * 请求实例
     */
    protected \think\Request $request;
    protected bool $requireAuth = false;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    /**
     * 初始化认证检查
     */
    protected function initialize(): void
    {
        if ($this->requireAuth && $this->getAuthUserId() <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }
    }

    /**
     * 成功响应（普通 / 分页 / 带消息）
     */
    protected function success(mixed $data = null, mixed $arg2 = null): Json
    {
        // 第二个参数是整数 → 分页响应
        if (is_int($arg2)) {
            $result = Result::page(
                $this->stringifyIds($data),
                $arg2
            );
            return $this->jsonResponse($result);
        }

        // 第二个参数是字符串 → 带消息响应
        $message = is_string($arg2) ? $arg2 : '';
        $result = Result::success(
            $this->stringifyIds($data),
            $message ?: ResultCode::SUCCESS->getMsg()
        );

        return $this->jsonResponse($result);
    }

    /**
     * 返回业务失败响应
     */
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
     * 输出 JSON 响应
     */
    private function jsonResponse(Result $result): Json
    {
        return json(
            CaseConverter::toCamelCase($result->toArray()),
            200,
            [],
            ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
        );
    }

    /**
     * 把数组里的 ID 字段转成字符串
     */
    protected function stringifyIds(mixed $data): mixed
    {
        return IdStringify::stringify($data);
    }

    /**
     * 执行数据校验，失败时抛异常
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
     * 获取 Service 实例
     */
    protected function service(string $class): object
    {
        return $this->app->make($class);
    }
}
