<?php declare(strict_types=1);

namespace extend\sse;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Predis\Client as RedisClient;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;
use Workerman\Worker;

/**
 * SSE 长连接 Worker（基于 Workerman）
 * 维护客户端连接，轮询 Redis 消息队列推送到对应用户
 */
class SseWorkerServer extends Worker
{
    private SseSessionRegistry $registry;
    private RedisClient $redis;

    private string $jwtSecret;

    private int $maxConnectionsPerUser = 3;
    private int $maxTotalConnections = 1000;
    private int $heartbeatInterval = 30;
    private int $pollInterval = 1;

    public function __construct(string $listen)
    {
        parent::__construct($listen);
        $this->registry = new SseSessionRegistry();
        $this->redis = $this->createRedisClient();
        $this->loadJwtConfig();
        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onMessage = [$this, 'onMessage'];
        $this->onWorkerStop = [$this, 'onWorkerStop'];
    }

    public function onWorkerStart(): void
    {
        Timer::add($this->pollInterval, function () {
            $this->drainRedisQueue();
        });
    }

    /**
     * 用 lpop 逐条取出，避免 lrange+del 的竞态（取和删之间新消息会丢失）
     */
    private function drainRedisQueue(): void
    {
        try {
            while (true) {
                $raw = $this->redis->lpop(SseTopics::REDIS_CHANNEL);
                if ($raw === null) break;

                $event = json_decode($raw, true);
                if ($event === null) continue;

                $eventName = $event['event'] ?? '';
                $data = $event['data'] ?? null;
                $targetUser = $event['targetUser'] ?? null;

                if ($eventName === '') continue;
                if ($eventName === SseTopics::ONLINE_COUNT) {
                    $data = $this->getGlobalOnlineCount();
                }

                $this->dispatch($eventName, $data, $targetUser);
            }
        } catch (\Throwable) {}
    }

    public function onMessage(TcpConnection $connection, Request $request): ?Response
    {
        $path = $request->path();
        $method = $request->method();

        if ($method === 'OPTIONS') {
            return new Response(204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        if ($path === '/health') {
            $connection->send(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));
            return null;
        }

        if ($path === '/api/v1/sse/connect' && $method === 'GET') {
            $this->handleSseConnect($connection, $request);
            return null;
        }

        $connection->send(new Response(404, ['Content-Type' => 'application/json'], '{"code":404,"message":"Not Found"}'));
        $connection->close();
        return null;
    }

    private function handleSseConnect(TcpConnection $connection, Request $request): void
    {
        $username = $this->authenticate($request);
        if ($username === '') {
            $connection->send(new Response(401, ['Content-Type' => 'application/json'], '{"code":401,"message":"Token 无效或已过期"}'));
            $connection->close();
            return;
        }

        if ($this->registry->getTotalConnectionCount() >= $this->maxTotalConnections) {
            $connection->send(new Response(503, ['Content-Type' => 'application/json'], '{"code":503,"message":"连接数已满"}'));
            $connection->close();
            return;
        }

        // 同一用户超过上限时踢掉最早的连接
        if ($this->registry->getUserEmitterCount($username) >= $this->maxConnectionsPerUser) {
            $oldest = $this->registry->getUserEmitters($username)[0] ?? null;
            if ($oldest !== null) {
                $oldest->close();
                $this->registry->removeEmitter($oldest);
            }
        }

        $emitter = new SseEmitter($connection);
        $onlineCount = $this->getGlobalOnlineCount();
        $emitter->sendHeadersWithEvent(SseTopics::ONLINE_COUNT, $onlineCount);

        $this->registry->userConnected($username, $emitter);
        $this->redis->hset(SseTopics::REDIS_ONLINE_KEY, $username, (string) time());
        $this->publishEvent(SseTopics::ONLINE_COUNT, $onlineCount);

        $timerId = Timer::add($this->heartbeatInterval, function () use ($emitter, &$timerId) {
            if ($emitter->isClosed()) { Timer::del($timerId); return; }
            $emitter->sendEvent('', '');
        });

        $connection->onClose = function (TcpConnection $conn) use ($emitter, $username, $timerId) {
            Timer::del($timerId);
            $emitter->close();
            $this->registry->removeEmitter($emitter);
            try {
                if ($this->registry->getUserEmitterCount($username) === 0) {
                    $this->redis->hdel(SseTopics::REDIS_ONLINE_KEY, $username);
                }
                $this->publishEvent(SseTopics::ONLINE_COUNT, $this->getGlobalOnlineCount());
            } catch (\Throwable) {}
        };
    }

    /**
     * 从 JWT 提取 username，回退到 user_id（兼容旧 token）
     */
    private function authenticate(Request $request): string
    {
        $token = $request->get('token', '');
        if ($token === '') {
            $authHeader = $request->header('authorization') ?? $request->header('Authorization');
            if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) return '';
            $token = substr($authHeader, 7);
        }
        if ($token === '') return '';

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $claims = json_decode(json_encode($decoded), true);
            return (string)($claims['username'] ?? $claims['user_id'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function dispatch(string $eventName, mixed $data, ?string $targetUser): void
    {
        $emitters = $targetUser !== null
            ? $this->registry->getUserEmitters($targetUser)
            : $this->registry->getAllEmitters();

        foreach ($emitters as $emitter) {
            try { $emitter->sendEvent($eventName, $data); }
            catch (\Throwable) { $this->registry->removeEmitter($emitter); }
        }
    }

    public function publishEvent(string $eventName, mixed $data): void
    {
        try {
            $this->redis->rpush(SseTopics::REDIS_CHANNEL, json_encode([
                'event'      => $eventName,
                'data'       => $data,
                'targetUser' => null,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {}
    }

    private function getGlobalOnlineCount(): int
    {
        try {
            return (int) $this->redis->hlen(SseTopics::REDIS_ONLINE_KEY);
        } catch (\Throwable) {
            return $this->registry->getOnlineUserCount();
        }
    }

    public function onWorkerStop(): void
    {
        foreach ($this->registry->getAllEmitters() as $emitter) {
            try { $emitter->close(); } catch (\Throwable) {}
        }
        try {
            foreach ($this->registry->getOnlineUsers() as $username) {
                $this->redis->hdel(SseTopics::REDIS_ONLINE_KEY, $username);
            }
        } catch (\Throwable) {}
    }

    private function createRedisClient(): RedisClient
    {
        $configPath = dirname(__DIR__, 2) . '/config/security.php';
        $cfg = file_exists($configPath) ? require $configPath : [];
        $redisCfg = $cfg['redis'] ?? [];

        $parameters = [
            'scheme'   => 'tcp',
            'host'     => $redisCfg['host'] ?? getenv('REDIS_HOST') ?: '127.0.0.1',
            'port'     => (int)($redisCfg['port'] ?? getenv('REDIS_PORT') ?: 6379),
            'database' => (int)($redisCfg['database'] ?? getenv('REDIS_DB') ?: 0),
        ];
        $password = $redisCfg['password'] ?? getenv('REDIS_PASSWORD') ?: '';
        if ($password !== '') $parameters['password'] = $password;

        return new RedisClient($parameters, ['prefix' => $redisCfg['prefix'] ?? getenv('REDIS_PREFIX') ?: '']);
    }

    private function loadJwtConfig(): void
    {
        $this->jwtSecret = '';
        $configPath = dirname(__DIR__, 2) . '/config/security.php';
        if (file_exists($configPath)) {
            $cfg = require $configPath;
            $this->jwtSecret = (string)($cfg['jwt']['secret'] ?? '');
        }
        if ($this->jwtSecret === '') {
            throw new \RuntimeException('JWT secret 未配置，请检查 config/security.php');
        }
    }
}
