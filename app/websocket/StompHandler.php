<?php declare(strict_types=1);

namespace app\websocket;

use app\common\security\TokenManagerResolver;
use app\websocket\stomp\Frame;
use app\websocket\stomp\Topics;
use think\facade\Log;
use think\Request;
use think\worker\contract\websocket\HandlerInterface;
use think\worker\Websocket;
use think\worker\websocket\Frame as WorkerFrame;

/**
 * STOMP 协议 WebSocket 处理器
 */
class StompHandler implements HandlerInterface
{
    protected UserSessionRegistry $registry;

    /** @var array<string, array> 已认证的会话信息 */
    protected array $authenticatedSessions = [];

    /** @var array<string, string> sessionId => senderId 映射 */
    protected array $senderMap = [];

    /** @var Websocket|null 当前 WebSocket 实例（由 onMessage 时设置） */
    protected ?Websocket $websocket = null;

    public function __construct(UserSessionRegistry $registry)
    {
        $this->registry = $registry;
        Log::info('✓ STOMP WebSocket Handler 已初始化');
    }

    /**
     * 连接打开
     */
    public function onOpen(Request $request): void
    {
        // 从 request 获取 sender ID（think-worker 设置的）
        $senderId = $this->getSenderId($request);
        Log::info("WebSocket 连接建立: senderId={$senderId}");
    }

    /**
     * 接收消息
     */
    public function onMessage(WorkerFrame $frame): void
    {
        $senderId = (string)$frame->fd;
        $data = $frame->data;

        // 解析 STOMP 帧
        $stompFrame = Frame::parse($data);
        if ($stompFrame === null) {
            Log::warning("无效的 STOMP 帧: senderId={$senderId}");
            return;
        }

        Log::debug("收到 STOMP 帧: command={$stompFrame->command}, senderId={$senderId}");

        try {
            switch ($stompFrame->command) {
                case 'CONNECT':
                case 'STOMP':
                    $this->handleConnect($senderId, $stompFrame);
                    break;

                case 'SUBSCRIBE':
                    $this->handleSubscribe($senderId, $stompFrame);
                    break;

                case 'UNSUBSCRIBE':
                    $this->handleUnsubscribe($senderId, $stompFrame);
                    break;

                case 'SEND':
                    $this->handleSend($senderId, $stompFrame);
                    break;

                case 'DISCONNECT':
                    $this->handleDisconnect($senderId, $stompFrame);
                    break;

                default:
                    Log::warning("未知的 STOMP 命令: {$stompFrame->command}");
            }
        } catch (\Throwable $e) {
            Log::error("STOMP 处理错误: " . $e->getMessage());
            $this->sendError($senderId, $e->getMessage());
        }
    }

    /**
     * 连接关闭
     */
    public function onClose(): void
    {
        // 由 think-worker 在 onClose 中调用，需要清理当前连接
        // 注意：此时无法获取 senderId，需要在其他地方处理
        Log::info("WebSocket 连接关闭");
    }

    /**
     * 清理指定会话
     */
    public function cleanupSession(string $senderId): void
    {
        $username = $this->registry->userDisconnected($senderId);
        unset($this->authenticatedSessions[$senderId]);
        unset($this->senderMap[$senderId]);

        if ($username) {
            Log::info("用户断开连接: user={$username}, senderId={$senderId}");
            $this->broadcastOnlineCount();
        }
    }

    /**
     * 处理连接请求
     */
    protected function handleConnect(string $senderId, Frame $frame): void
    {
        $authorization = $frame->getHeader('Authorization', $frame->getHeader('login', ''));

        if (empty($authorization)) {
            $this->sendError($senderId, 'Missing Authorization header');
            return;
        }

        // 解析 Bearer Token
        $token = $authorization;
        if (str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);
        }

        if (empty($token)) {
            $this->sendError($senderId, 'Token is empty');
            return;
        }

        // 验证 Token
        try {
            $tokenManager = new TokenManagerResolver();
            $user = $tokenManager->get()->parseAccessToken($token);

            if (empty($user['userId'])) {
                throw new \Exception('Invalid token');
            }

            $username = (string)($user['username'] ?? $user['userId']);

            // 注册用户会话
            $this->registry->userConnected($username, $senderId);
            $this->authenticatedSessions[$senderId] = [
                'username' => $username,
                'userId' => $user['userId'],
                'user' => $user,
            ];
            $this->senderMap[$senderId] = $senderId;

            // 发送 CONNECTED 帧
            $connectedFrame = new Frame('CONNECTED', [
                'version' => '1.2',
                'heart-beat' => '4000,4000',
                'server' => 'youlai-think-stomp/1.0',
            ]);
            $this->send($senderId, $connectedFrame);

            Log::info("✓ STOMP 连接成功: user={$username}, senderId={$senderId}");

            // 广播在线用户数
            $this->broadcastOnlineCount();

        } catch (\Throwable $e) {
            Log::error("Token 验证失败: " . $e->getMessage());
            $this->sendError($senderId, 'Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * 处理订阅请求
     */
    protected function handleSubscribe(string $senderId, Frame $frame): void
    {
        $destination = $frame->getHeader('destination');
        $subscriptionId = $frame->getHeader('id');

        if (empty($destination) || empty($subscriptionId)) {
            $this->sendError($senderId, 'Missing destination or id');
            return;
        }

        // 检查是否已认证
        if (!isset($this->authenticatedSessions[$senderId])) {
            $this->sendError($senderId, 'Not authenticated');
            return;
        }

        // 添加订阅
        $this->registry->addSubscription($senderId, $destination, $subscriptionId);

        Log::info("订阅成功: senderId={$senderId}, destination={$destination}");

        // 发送收据（如果请求）
        $receipt = $frame->getHeader('receipt');
        if ($receipt) {
            $this->sendReceipt($senderId, $receipt);
        }
    }

    /**
     * 处理取消订阅
     */
    protected function handleUnsubscribe(string $senderId, Frame $frame): void
    {
        $subscriptionId = $frame->getHeader('id');
        if ($subscriptionId) {
            $this->registry->removeSubscription($senderId, $subscriptionId);
        }
    }

    /**
     * 处理发送消息
     */
    protected function handleSend(string $senderId, Frame $frame): void
    {
        $destination = $frame->getHeader('destination');
        $body = $frame->body;

        Log::debug("收到消息: senderId={$senderId}, destination={$destination}, body={$body}");
    }

    /**
     * 处理断开连接
     */
    protected function handleDisconnect(string $senderId, Frame $frame): void
    {
        $receipt = $frame->getHeader('receipt');
        if ($receipt) {
            $this->sendReceipt($senderId, $receipt);
        }

        $this->cleanupSession($senderId);
    }

    /**
     * 发送消息到指定 sender
     */
    protected function send(string $senderId, Frame $frame): void
    {
        // 使用 think-worker 的推送机制
        // 通过 websocket 实例发送
        if ($this->websocket) {
            $this->websocket->to($senderId)->push($frame->encode());
        }
    }

    /**
     * 发送错误帧
     */
    protected function sendError(string $senderId, string $message): void
    {
        $errorFrame = new Frame('ERROR', [
            'message' => $message,
        ]);
        $this->send($senderId, $errorFrame);
    }

    /**
     * 发送收据
     */
    protected function sendReceipt(string $senderId, string $receiptId): void
    {
        $receiptFrame = new Frame('RECEIPT', [
            'receipt-id' => $receiptId,
        ]);
        $this->send($senderId, $receiptFrame);
    }

    /**
     * 广播消息到所有订阅者
     */
    public function broadcast(string $destination, mixed $data): void
    {
        $sessions = $this->registry->getSessionsByDestination($destination);

        foreach ($sessions as $senderId) {
            $subscriptions = $this->registry->getSubscriptions($senderId);
            foreach ($subscriptions as $subId => $dest) {
                if ($dest === $destination) {
                    $messageFrame = new Frame('MESSAGE', [
                        'destination' => $destination,
                        'subscription' => $subId,
                        'message-id' => uniqid('msg-', true),
                        'content-type' => 'application/json',
                    ], is_string($data) ? $data : json_encode($data));
                    $this->send($senderId, $messageFrame);
                }
            }
        }
    }

    /**
     * 发送消息给指定用户
     */
    public function sendToUser(string $username, string $destination, mixed $data): void
    {
        $sessions = $this->registry->getUserSessions($username);

        foreach ($sessions as $senderId) {
            $subscriptions = $this->registry->getSubscriptions($senderId);
            foreach ($subscriptions as $subId => $dest) {
                if ($dest === $destination) {
                    $messageFrame = new Frame('MESSAGE', [
                        'destination' => $destination,
                        'subscription' => $subId,
                        'message-id' => uniqid('msg-', true),
                        'content-type' => 'application/json',
                    ], is_string($data) ? $data : json_encode($data));
                    $this->send($senderId, $messageFrame);
                }
            }
        }
    }

    /**
     * 广播在线用户数
     */
    protected function broadcastOnlineCount(): void
    {
        $count = $this->registry->getOnlineUserCount();
        $this->broadcast(Topics::TOPIC_ONLINE_COUNT, $count);
    }

    /**
     * 编码消息（think-worker 调用）
     */
    public function encodeMessage($message): string
    {
        if ($message instanceof Frame) {
            return $message->encode();
        }
        if (is_string($message)) {
            return $message;
        }
        return json_encode($message);
    }

    /**
     * 设置 WebSocket 实例
     */
    public function setWebsocket(Websocket $websocket): void
    {
        $this->websocket = $websocket;
    }

    /**
     * 从 Request 获取 sender ID
     */
    protected function getSenderId(Request $request): string
    {
        return (string)$request->fd;
    }

    /**
     * 获取在线用户数
     */
    public function getOnlineUserCount(): int
    {
        return $this->registry->getOnlineUserCount();
    }

    /**
     * 获取在线用户列表
     */
    public function getOnlineUsers(): array
    {
        return $this->registry->getOnlineUsers();
    }
}
