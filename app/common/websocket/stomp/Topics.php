<?php declare(strict_types=1);

namespace app\common\websocket\stomp;

/**
 * STOMP 主题常量
 */
final class Topics
{
    // 广播主题
    public const TOPIC_DICT = '/topic/dict';
    public const TOPIC_ONLINE_COUNT = '/topic/online-count';
    public const TOPIC_PUBLIC = '/topic/public';

    // 用户队列
    public const USER_QUEUE_MESSAGES = '/queue/messages';
    public const USER_QUEUE_MESSAGE = '/queue/message';

    private function __construct()
    {
    }
}
