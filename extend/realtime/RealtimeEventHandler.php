<?php declare(strict_types=1);

namespace extend\realtime;

use GatewayWorker\Lib\Gateway;
use extend\jwt\TokenManagerResolver;

/**
 * 实时连接事件处理，负责握手校验、分组绑定与心跳响应
 */
class RealtimeEventHandler
{
    // 连接上下文：clientId => username、topics、lastPongAt
    private static array $sessions = [];

    /**
     * 全部连接上下文
     */
    public static function sessions(): array
    {
        return self::$sessions;
    }

    /**
     * WS 握手：从 URL 查询参数取票据并校验，client_id 为 GatewayWorker 的字符串标识
     */
    public static function onWebSocketConnect(string $clientId, array $data): void
    {
        $ticket = (string) ($data['get']['ticket'] ?? '');
        $grant = RealtimeTicketService::getInstance()->consume($ticket);
        if ($grant === null) {
            Gateway::closeClient($clientId, 4001, 'invalid ticket');
            return;
        }

        $accessToken = (string) ($grant['accessToken'] ?? '');
        try {
            $authUser = TokenManagerResolver::resolve()->parseAccessToken($accessToken);
        } catch (\Throwable) {
            $authUser = null;
        }
        if ($authUser === null) {
            Gateway::closeClient($clientId, 4001, 'invalid credential');
            return;
        }

        $username = (string) ($grant['username'] ?? '');
        $topics = is_array($grant['topics'] ?? null) ? $grant['topics'] : [];
        foreach ($topics as $topic) {
            Gateway::joinGroup($clientId, RealtimeService::groupName((string) $topic));
        }
        Gateway::bindUid($clientId, $username);
        Gateway::updateClient($clientId, ['username' => $username]);

        self::$sessions[$clientId] = [
            'username' => $username,
            'topics' => $topics,
            'lastPongAt' => time(),
        ];

        Gateway::sendToClient(
            $clientId,
            RealtimeService::encode(RealtimeTopics::EVENT_CONNECTION_READY, [
                'heartbeatTimeoutMs' => RealtimeTopics::PONG_TIMEOUT * 1000,
            ])
        );
    }

    /**
     * 客户端消息，只处理心跳响应
     */
    public static function onMessage(string $clientId, string $message): void
    {
        $payload = json_decode($message, true);
        if (!is_array($payload) || ($payload['version'] ?? 0) !== 1) {
            return;
        }
        if (($payload['type'] ?? '') !== RealtimeTopics::EVENT_HEARTBEAT_PONG) {
            return;
        }
        if (!isset(self::$sessions[$clientId])) {
            return;
        }
        self::$sessions[$clientId]['lastPongAt'] = time();
    }

    /**
     * 客户端断开
     */
    public static function onClose(string $clientId): void
    {
        unset(self::$sessions[$clientId]);
    }
}
