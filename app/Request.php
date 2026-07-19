<?php declare(strict_types=1);

namespace app;

use think\Request as BaseRequest;

/**
 * @property array|null $snakeParams  已转换为 snake_case 的请求参数
 * @property array|null $authUser     当前认证用户
 */
class Request extends BaseRequest
{
    public ?array $snakeParams = null;
    public ?array $authUser = null;
}
