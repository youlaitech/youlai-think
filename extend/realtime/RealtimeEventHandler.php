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
     * 客户端连接，握手只接受一次性票据
     */
    public static function onConnect(string $clientId, $request): void
    {
        $ticket = (string) ($request->get('ticket') ?? '');
        $grant = RealtimeTicketService::getInstance()->consume($ticket);
        if ($grant === null) {
            Gateway::closeClient((int) $clientId, 4001, 'invalid ticket');
            return;
        }

        $accessToken = (string) ($grant['accessToken'] ?? '');
        try {
            $authUser = TokenManagerResolver::resolve()->parseAccessToken($accessToken);
        } catch (\Throwable) {
            $authUser = null;
        }
        if ($authUser === null) {
            Gateway::closeClient((int) $clientId, 4001, 'invalid credential');
            return;
        }

        $username = (string) ($grant['username'] ?? '');
        $topics = is_array($grant['topics'] ?? null) ? $grant['topics'] : [];
        foreach ($topics as $topic) {
            Gateway::joinGroup((int) $clientId, RealtimeService::groupName((string) $topic));
        }
        Gateway::bindUid((int) $clientId, $username);
        Gateway::updateClient((int) $clientId, ['username' => $username]);

        self::$sessions[$clientId] = [
            'username' => $username,
            'topics' => $topics,
            'lastPongAt' => time(),
        ];

        Gateway::sendToClient(
            (int) $clientId,
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