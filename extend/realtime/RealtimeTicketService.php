<?php declare(strict_types=1);

namespace extend\realtime;

use extend\redis\RedisClient;

/**
 * 实时连接一次性票据，握手时取出即删除
 */
class RealtimeTicketService
{
    /**
     * 票据有效期（秒）
     */
    public const TICKET_TTL = 30;

    /**
     * Redis 键前缀
     */
    private const KEY_PREFIX = 'realtime:ticket:';

    private static ?self $instance = null;

    /**
     * 获取全局实例
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 签发票据，Redis 键只保存票据摘要
     */
    public function create(string $username, string $accessToken, array $topics): string
    {
        $ticket = bin2hex(random_bytes(24));
        $grant = [
            'username' => $username,
            'accessToken' => $accessToken,
            'topics' => array_values($topics),
            'expiresAt' => time() + self::TICKET_TTL,
        ];

        RedisClient::get()->setex(
            self::KEY_PREFIX . self::digest($ticket),
            self::TICKET_TTL,
            json_encode($grant, JSON_UNESCAPED_UNICODE)
        );

        return $ticket;
    }

    /**
     * 取出并删除票据，无效、已使用或已过期返回 null
     */
    public function consume(string $ticket): ?array
    {
        if ($ticket === '' || strlen($ticket) < 43 || strlen($ticket) > 256) {
            return null;
        }

        $key = self::KEY_PREFIX . self::digest($ticket);
        $redis = RedisClient::get();
        $raw = $redis->get($key);
        if (!$raw) {
            return null;
        }
        $redis->del($key);

        $grant = json_decode((string) $raw, true);
        if (!is_array($grant) || ($grant['expiresAt'] ?? 0) <= time()) {
            return null;
        }

        return $grant;
    }

    /**
     * 票据摘要
     */
    private static function digest(string $ticket): string
    {
        return hash('sha256', $ticket);
    }
}