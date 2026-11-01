<?php declare(strict_types=1);

namespace app\auth\controller;

use app\controller\BaseController;
use GatewayWorker\Lib\Gateway;
use extend\realtime\RealtimeTicketService;
use extend\realtime\RealtimeTopics;
use OpenApi\Annotations as OA;
use think\response\Json;

/**
 * @OA\Tag(name="14.实时推送")
 */
final class RealtimeController extends BaseController
{
    /**
     * 签发实时连接票据
     *
     * @OA\Post(
     *     path="/api/v1/realtime/tickets",
     *     summary="签发实时连接票据",
     *     tags={"14.实时推送"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function tickets(): Json
    {
        $topics = $this->request->post('topics', []);
        if (!is_array($topics) || $topics === []) {
            $topics = $this->getParam('topics', []);
        }
        if (!is_array($topics)) {
            $topics = $topics === '' ? [] : explode(',', (string) $topics);
        }
        $topics = array_values(array_filter(array_map('strval', $topics)));

        if (!in_array(RealtimeTopics::TOPIC_NOTICE, $topics, true)) {
            return $this->fail('A0400', '订阅主题须包含 notice');
        }
        foreach ($topics as $topic) {
            if (!in_array($topic, RealtimeTopics::SUPPORTED_TOPICS, true)) {
                return $this->fail('A0400', "不支持的订阅主题：{$topic}");
            }
        }

        $username = $this->currentUsername();
        if ($username === '') {
            return $this->fail('A0401', '未获取到当前用户');
        }

        $ticket = RealtimeTicketService::getInstance()->create($username, $this->accessToken(), $topics);

        return $this->success(['ticket' => $ticket]);
    }

    /**
     * 查询在线状态
     *
     * @OA\Get(
     *     path="/api/v1/realtime/presence",
     *     summary="查询在线状态",
     *     tags={"14.实时推送"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function presence(): Json
    {
        return $this->success(RealtimeService::getInstance()->presenceSnapshot());
    }

    /**
     * 读取认证中间件挂载的当前用户名
     */
    private function currentUsername(): string
    {
        $authUser = $this->request->authUser ?? null;
        if (is_array($authUser)) {
            return (string) ($authUser['username'] ?? '');
        }
        if (is_object($authUser)) {
            return (string) ($authUser->username ?? '');
        }
        return '';
    }

    /**
     * 读取请求头中的访问令牌，用于握手时复核凭证
     */
    private function accessToken(): string
    {
        $headerName = (string) config('security.token_header');
        $tokenPrefix = (string) config('security.token_prefix');

        $raw = (string) $this->request->header($headerName);
        if ($raw === '') {
            $raw = (string) $this->request->header(strtolower($headerName));
        }
        if ($tokenPrefix !== '' && str_starts_with($raw, $tokenPrefix)) {
            $raw = substr($raw, strlen($tokenPrefix));
        }

        return trim($raw);
    }
}