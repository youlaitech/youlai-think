<?php declare(strict_types=1);

namespace extend\realtime;

use GatewayWorker\Lib\Gateway;

/**
 * 实时推送服务，通过 GatewayWorker 分发消息
 */
class RealtimeService
{
    /**
     * 节点标识，用于前端区分多节点部署
     */
    public const NODE_ID = 'youlai-think';

    private static ?self $instance = null;

    /**
     * 获取全局实例
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 按主题广播消息
     */
    public function broadcast(string $topic, string $type, mixed $payload): void
    {
        Gateway::sendToGroup(self::groupName($topic), self::encode($type, $payload));
    }

    /**
     * 定向推送给指定用户
     */
    public function sendToUser(string $username, string $type, mixed $payload): void
    {
        Gateway::sendToUid($username, self::encode($type, $payload));
    }

    /**
     * 广播字典变更
     */
    public function sendDictChange(string $dictCode): void
    {
        if ($dictCode === '') {
            return;
        }
        $this->broadcast(RealtimeTopics::TOPIC_DICT, RealtimeTopics::EVENT_DICT_CHANGED, [
            'dictCode' => $dictCode,
        ]);
    }

    /**
     * 广播在线状态快照
     */
    public function sendOnlineCount(): void
    {
        $this->broadcast(RealtimeTopics::TOPIC_PRESENCE, RealtimeTopics::EVENT_PRESENCE_SNAPSHOT, $this->presenceSnapshot());
    }

    /**
     * 生成在线状态快照，GatewayWorker 按连接计数
     */
    public function presenceSnapshot(): array
    {
        $clientIds = Gateway::getAllClientId();
        $count = count($clientIds);

        return [
            'onlineUsers' => $count,
            'onlineConnections' => $count,
            'nodeId' => self::NODE_ID,
            'observedAt' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];
    }

    /**
     * 定时发送心跳，超时未响应则断开连接
     */
    public function sendHeartbeat(): void
    {
        $now = time();
        foreach (RealtimeEventHandler::sessions() as $clientId => $session) {
            if ($now - $session['lastPongAt'] >= RealtimeTopics::PONG_TIMEOUT) {
                Gateway::closeClient((int) $clientId, 4011, 'heartbeat timeout');
                continue;
            }
            Gateway::sendToClient((int) $clientId, self::encode(RealtimeTopics::EVENT_HEARTBEAT_PING, []));
        }
    }

    /**
     * 封装协议消息
     */
    public static function encode(string $type, mixed $payload): string
    {
        return json_encode([
            'version' => 1,
            'id' => bin2hex(random_bytes(16)),
            'type' => $type,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 主题对应的分组名
     */
    public static function groupName(string $topic): string
    {
        return 'topic_' . $topic;
    }
}