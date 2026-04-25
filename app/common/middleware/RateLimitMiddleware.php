<?php declare(strict_types=1);

namespace app\common\middleware;

use app\common\util\CaseConverter;
use app\common\web\Result;
use app\common\web\ResultCode;
use extend\redis\RedisClient;
use think\Response;

/**
 * IP 限流中间件
 */
final class RateLimitMiddleware
{
    private const int DEFAULT_IP_LIMIT = 10;
    private const int RATE_LIMIT_WINDOW_SEC = 1;

    /**
     * 超过并发限制时返回 429
     */
    public function handle(\think\Request $request, \Closure $next): Response
    {
        if (strtoupper((string) $request->method()) === 'OPTIONS') {
            return $next($request);
        }
        $ip = $request->ip();
        if ($this->isRateLimited($ip)) {
            $result = Result::failedWith(ResultCode::REQUEST_CONCURRENCY_LIMIT_EXCEEDED, '请求并发数超出限制');
            return json(CaseConverter::toCamelCase($result->toArray()))->code(429);
        }
        $response = $next($request);
        return $response instanceof Response ? $response : response($response);
    }

    /**
     * 检查 IP 是否超出请求限制
     */
    private function isRateLimited(string $ip): bool
    {
        try {
            $redis = RedisClient::get();
            $key = \app\constants\\RedisKey::rateLimitIp($ip);
            $count = $redis->incr($key);
            if ($count === 1) $redis->expire($key, self::RATE_LIMIT_WINDOW_SEC);
            return $count > self::DEFAULT_IP_LIMIT;
        } catch (\Throwable) { return false; }
    }
}
