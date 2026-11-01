<?php declare(strict_types=1);

namespace extend\realtime;

/**
 * 实时推送主题与消息类型
 */
class RealtimeTopics
{
    /**
     * 订阅主题，连接必须包含 notice
     */
    public const TOPIC_NOTICE = 'notice';
    public const TOPIC_DICT = 'dict';
    public const TOPIC_PRESENCE = 'presence';

    /**
     * 当前支持的全部主题
     */
    public const SUPPORTED_TOPICS = [self::TOPIC_NOTICE, self::TOPIC_DICT, self::TOPIC_PRESENCE];

    /**
     * 消息类型
     */
    public const EVENT_CONNECTION_READY = 'connection.ready';
    public const EVENT_HEARTBEAT_PING = 'heartbeat.ping';
    public const EVENT_NOTICE_CHANGED = 'notice.changed';
    public const EVENT_DICT_CHANGED = 'dict.changed';
    public const EVENT_PRESENCE_SNAPSHOT = 'presence.snapshot';
    public const EVENT_HEARTBEAT_PONG = 'heartbeat.pong';

    /**
     * 心跳应答超时时间（秒），与前端协议保持一致
     */
    public const PONG_TIMEOUT = 60;
}