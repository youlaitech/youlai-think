<?php declare(strict_types=1);

namespace extend\sse;

use app\common\constants\NoticeEvents;
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
 * 处理心跳、Token 校验、Redis Pub/Sub 消息推送
 */
class SseWorkerServer extends Worker
{
    private SseSessionRegistry $registry;
    private RedisClient $redis;

    private const REDIS_SSE_CHANNEL = 'sse:broadcast';
    private const REDIS_ONLINE_KEY = 'sse:online_users';

    private string $jwtSecret;
    private string $jwtIssuer;

    private int $maxConnectionsPerUser = 3;
    private int $maxTotalConnections = 1000;
    private int $heartbeatInterval = 30;
    private int $pubsubPollInterval = 1;

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
        Timer::add($this->pubsubPollInterval, function () {
            try {
                $events = $this->redis->lrange(self::REDIS_SSE_CHANNEL, 0, -1);
                if (empty($events)) return;
                $this->redis->del(self::REDIS_SSE_CHANNEL);
                foreach ($events as $eventJson) {
                    $event = json_decode($eventJson, true);
                    if ($event === null) continue;
                    $eventName = $event['event'] ?? '';
                    $data = $event['data'] ?? null;
                    $targetUser = $event['targetUser'] ?? null;
                    if ($eventName === '') continue;
                    if ($eventName === SseTopics::ONLINE_COUNT) $data = $this->getGlobalOnlineCount();
                    $this->broadcastInProcess($eventName, $data, $targetUser);
                }
            } catch (\Throwable) {}
        });
    }

    public function onMessage(TcpConnection $connection, Request $request): ?Response
    {
        $path = $request->path();
        $method = $request->method();

        // 调试日志
        $runtimeDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'runtime';
        if (!is_dir($runtimeDir)) {
            @mkdir($runtimeDir, 0777, true);
        }
        $logFile = $runtimeDir . DIRECTORY_SEPARATOR . 'sse_debug.log';
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " {$method} {$path}\n", FILE_APPEND);

        // CORS 预检请求 - 直接返回 Response 对象
        if ($method === 'OPTIONS') {
            return new Response(204, [
                'Access-Control-Allow-Origin' => '*',
                'Vary' => 'Origin',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Origin',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        if ($path === '/health') {
            $connection->send(new Response(200, ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'], json_encode(['status' => 'ok'])));
            return null;
        }

        if ($path === '/api/v1/sse/connect' && $request->method() === 'GET') {
            $this->handleSseConnect($connection, $request);
            return null;
        }

        $connection->send(new Response(404, ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'], json_encode(['code' => 404, 'message' => 'Not Found'])));
        $connection->close();
        return null;
    }

    private function handleSseConnect(TcpConnection $connection, Request $request): void
    {
        $userId = $this->authenticate($request);
        if ($userId <= 0) {
            $connection->send(new Response(401, ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'], json_encode(['code' => 401, 'message' => '未授权：Token 无效或已过期'])));
            $connection->close();
            return;
        }
        $username = (string)$userId;

        if ($this->registry->getTotalConnectionCount() >= $this->maxTotalConnections) {
            $connection->send(new Response(503, ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'], json_encode(['code' => 503, 'message' => '服务器在线连接数已满'])));
            $connection->close();
            return;
        }

        if ($this->registry->getUserEmitterCount($username) >= $this->maxConnectionsPerUser) {
            $emitters = $this->registry->getUserEmitters($username);
            if (!empty($emitters) && $emitters[0] instanceof SseEmitter) { $emitters[0]->close(); $this->registry->removeEmitter($emitters[0]); }
        }

        // 将 HTTP 响应头和初始 SSE 事件合并为一次 raw send
        // 避免代理在收到空响应体后关闭连接（0.5s 延迟期间代理认为响应已完成）
        $emitter = new SseEmitter($connection);
        $onlineCount = $this->getGlobalOnlineCount();
        $emitter->sendHeadersWithEvent("online-count", $onlineCount);

        $this->registry->userConnected($username, $emitter);
        $this->redis->hset(self::REDIS_ONLINE_KEY, $username, (string)time());
        $this->publishEvent(SseTopics::ONLINE_COUNT, $onlineCount);

        $timerId = Timer::add($this->heartbeatInterval, function () use ($emitter, &$timerId) {
            if ($emitter->isClosed()) { Timer::del($timerId); return; }
            try { $emitter->sendEvent("", ""); } catch (\Throwable) { $emitter->close(); Timer::del($timerId); }
        });

        $registry = $this->registry; $redis = $this->redis; $worker = $this;
        $connection->onClose = function (TcpConnection $conn) use ($emitter, $username, $registry, $timerId, $redis, $worker) {
            Timer::del($timerId);
            if (!$emitter->isClosed()) $emitter->close();
            $registry->removeEmitter($emitter);
            try {
                if ($registry->getUserEmitterCount($username) === 0) $redis->hdel(SseWorkerServer::REDIS_ONLINE_KEY, $username);
                $worker->publishEvent(SseTopics::ONLINE_COUNT, $worker->getGlobalOnlineCount());
            } catch (\Throwable) {}
        };
    }

    private function authenticate(Request $request): int
    {
        // 优先从 URL 参数获取 token（EventSource 不支持自定义 header）
        $token = $request->get('token', '');
        if (empty($token)) {
            // 其次从 Authorization header 获取
            $authHeader = $request->header('authorization') ?? $request->header('Authorization');
            if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) return 0;
            $token = substr($authHeader, 7);
        }
        if ($token === '') return 0;
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (int)(json_decode(json_encode($decoded), true)['user_id'] ?? 0);
        } catch (\Throwable) { return 0; }
    }

    private function broadcastInProcess(string $eventName, mixed $data, ?string $targetUser = null): void
    {
        $emitters = $targetUser !== null ? $this->registry->getUserEmitters($targetUser) : $this->registry->getAllEmitters();
        foreach ($emitters as $emitter) {
            try { $emitter->sendEvent($eventName, $data); } catch (\Throwable) { $this->registry->removeEmitter($emitter); }
        }
    }

    public function publishEvent(string $eventName, mixed $data): void
    {
        try { $this->redis->rpush(self::REDIS_SSE_CHANNEL, json_encode(['event' => $eventName, 'data' => $data], JSON_UNESCAPED_UNICODE)); } catch (\Throwable) {}
    }

    private function getGlobalOnlineCount(): int
    {
        try { return (int)$this->redis->hlen(self::REDIS_ONLINE_KEY); } catch (\Throwable) { return $this->registry->getOnlineUserCount(); }
    }

    public function onWorkerStop(): void
    {
        // Worker 进程退出时主动关闭所有 SSE 连接
        $emitters = $this->registry->getAllEmitters();
        foreach ($emitters as $emitter) {
            try { $emitter->close(); } catch (\Throwable) {}
        }
        try {
            $this->registry->getOnlineUsers();
            // 清理 Redis 在线用户数据
            $users = $this->registry->getOnlineUsers();
            foreach ($users as $username) {
                $this->redis->hdel(self::REDIS_ONLINE_KEY, $username);
            }
        } catch (\Throwable) {}
    }

    private function createRedisClient(): RedisClient
    {
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);
        $password = getenv('REDIS_PASSWORD') ?: '';
        $database = (int)(getenv('REDIS_DB') ?: 0);
        $prefix = getenv('REDIS_PREFIX') ?: '';

        $configPath = dirname(__DIR__, 2) . '/config/security.php';
        if (file_exists($configPath)) {
            $cfg = require $configPath;
            $redisCfg = $cfg['redis'] ?? [];
            $host = $redisCfg['host'] ?? $host;
            $port = (int)($redisCfg['port'] ?? $port);
            $password = $redisCfg['password'] ?? $password;
            $database = (int)($redisCfg['database'] ?? $database);
            $prefix = $redisCfg['prefix'] ?? $prefix;
        }

        $parameters = ['scheme' => 'tcp', 'host' => $host, 'port' => $port, 'database' => $database];
        if (!empty($password)) $parameters['password'] = $password;
        return new RedisClient($parameters, ['prefix' => $prefix]);
    }

    private function loadJwtConfig(): void
    {
        $this->jwtSecret = '';
        $this->jwtIssuer = '';

        $configPath = dirname(__DIR__, 2) . '/config/security.php';
        if (file_exists($configPath)) {
            $cfg = require $configPath;
            $jwtCfg = $cfg['jwt'] ?? [];
            $this->jwtSecret = (string)($jwtCfg['secret'] ?? '');
            $this->jwtIssuer = (string)($jwtCfg['issuer'] ?? '');
        }

        if ($this->jwtSecret === '') {
            throw new \RuntimeException('SSE 服务启动失败：JWT secret 未配置，请检查 config/security.php 和 .env');
        }
    }
}
