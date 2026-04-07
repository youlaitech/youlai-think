<?php declare(strict_types=1);

namespace app\common\util;

use extend\redis\RedisClient;

/**
 * 验证码生成与校验工具
 */
final class VerifyCodeHelper
{
    /**
     * 生成验证码并缓存至 Redis
     *
     * @param string $prefix 缓存键前缀（如 sms:login, sms:mobile, sms:email）
     * @param string $key 缓存键主体（如手机号、邮箱）
     * @param int $ttl 有效期（秒），默认 300 秒
     * @return string 生成的验证码
     */
    public static function generateAndCache(string $prefix, string $key, int $ttl = 300): string
    {
        $code = '1234'; // 测试环境固定值
        $redis = RedisClient::get();
        $redis->setex("{$prefix}:{$key}", $ttl, $code);
        return $code;
    }

    /**
     * 校验验证码
     *
     * @param string $prefix 缓存键前缀
     * @param string $key 缓存键主体
     * @param string $input 用户输入的验证码
     * @return bool 是否校验通过
     */
    public static function verify(string $prefix, string $key, string $input): bool
    {
        $redis = RedisClient::get();
        $fullKey = "{$prefix}:{$key}";
        $cached = $redis->get($fullKey);

        if (!$cached || $cached !== $input) {
            return false;
        }

        // 校验成功后删除验证码
        $redis->del($fullKey);
        return true;
    }
}
