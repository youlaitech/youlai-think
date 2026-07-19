<?php

namespace app\middleware;

use app\common\web\Result;
use app\common\web\ResultCode;
use think\facade\Cache;
use Closure;

// IP 滑动窗口限流中间件
class RateLimitMiddleware
{
    private const LUA_SLIDING_WINDOW = <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
local member = ARGV[3]
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
redis.call('ZADD', key, now, member)
redis.call('PEXPIRE', key, window + 1000)
return redis.call('ZCARD', key)
LUA;

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
            $redis = Cache::store('redis')->handler();
            $count = $redis->eval(self::LUA_SLIDING_WINDOW, [$key, $now, $windowMs, $member], 1);

            if ($count > $limit) {
                // 窗口内请求数已超上限，返回 429 并附限流头
                return json(
                    Result::failedWith(ResultCode::REQUEST_CONCURRENCY_LIMIT_EXCEEDED)->toArray(),
                    429,
                    [],
                    ['json_encode_param' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]
                )->header('Retry-After', (string) $windowSec);
            }
        } catch (\Throwable) {
            // Redis 不可用时放行
            return $next($request);
        }

        // 正常放行时回写限流状态头（窗口内剩余可用请求数、窗口重置时间）
        $response = $next($request);
        $response->header('X-RateLimit-Limit', (string) $limit);
        $response->header('X-RateLimit-Remaining', (string) max(0, $limit - (int) $count));
        $response->header('X-RateLimit-Reset', (string) (time() + $windowSec));

        return $response;
    }
}
