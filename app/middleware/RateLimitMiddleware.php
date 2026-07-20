<?php

namespace app\middleware;

use app\common\web\Result;
use app\common\web\ResultCode;
use extend\redis\RedisClient;
use Closure;

// IP 滑动窗口限流中间件
class RateLimitMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!config('rate-limit.ip.enabled', true)) {
            return $next($request);
        }

        $ip = $request->ip();
        $key = "rate_limit:ip:{$ip}";
        $windowSec = (int) config('rate-limit.ip.window', 60);
        $windowMs = $windowSec * 1000;
        $limit = (int) config('rate-limit.ip.limit', 1000);
        $now = intval(microtime(true) * 1000);
        $member = uniqid('', true);

        try {
            $redis = RedisClient::get();
            // 滑动窗口：剔除窗口外的旧请求，写入本次请求，统计窗口内请求数
            $redis->zremrangebyscore($key, 0, $now - $windowMs);
            $redis->zadd($key, $now, $member);
            $redis->pexpire($key, $windowMs + 1000);
            $count = $redis->zcard($key);

            if ($count > $limit) {
                // 窗口内请求数已超上限，返回 429 并附限流头
                return json(
                    Result::failedWith(ResultCode::REQUEST_CONCURRENCY_LIMIT_EXCEEDED)->toArray(),
                    429,
                    [],
                    ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
                )->header(['Retry-After' => (string) $windowSec]);
            }
        } catch (\Throwable) {
            // Redis 不可用时放行
            return $next($request);
        }

        // 正常放行时回写限流状态头（窗口内剩余可用请求数、窗口重置时间）
        $response = $next($request);
        $response->header([
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) max(0, $limit - (int) $count),
            'X-RateLimit-Reset' => (string) (time() + $windowSec),
        ]);

        return $response;
    }
}
