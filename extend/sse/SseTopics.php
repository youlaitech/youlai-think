<?php declare(strict_types=1);

namespace extend\sse;

/**
 * SSE 常量定义：事件名称 + Redis 键名
 */
class SseTopics
{
    // 事件名称
    public const DICT         = 'dict';
    public const ONLINE_COUNT = 'online-count';
    public const SYSTEM       = 'system';
    public const NOTICE       = 'notice';
    public const NOTICE_REVOKE = 'notice-revoke';

    // Redis 键名
    public const REDIS_CHANNEL    = 'sse:broadcast';
    public const REDIS_ONLINE_KEY = 'sse:online_users';
}
