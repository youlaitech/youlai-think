<?php declare(strict_types=1);

namespace app\common\sse;

use app\BaseController;
use think\Response;
use think\facade\Log;

/**
 * SSE 控制器
 */
final class SseController extends BaseController
{
    protected bool $requireAuth = true;

    /**
     * SSE连接接口
     */
    public function connect(): Response
    {
        $username = $this->getAuthUsername();
        if (!$username) {
            return $this->fail('A0001', '未授权');
        }

        $response = new Response();
        $response->header([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        $sseService = SseService::getInstance();
        $emitter = new SseEmitter();
        $sseService->createConnection($username, $emitter);

        // 生成SSE流
        $output = '';
        $output .= "event: online-count\n";
        $output .= "data: " . $sseService->getOnlineUserCount() . "\n\n";

        // 心跳和事件循环
        while (!$emitter->isClosed()) {
            $event = $emitter->getLastEvent();
            if ($event) {
                $output .= "event: " . $event['event'] . "\n";
                $output .= "data: " . json_encode($event['data'], JSON_UNESCAPED_UNICODE) . "\n\n";
                $emitter->clearLastEvent();
            } else {
                $output .= ": heartbeat\n\n";
            }
            $emitter->close(); // 简化处理，实际应保持连接
        }

        $sseService->removeEmitter($emitter);
        $response->content($output);
        return $response;
    }

    /**
     * 获取在线用户数
     */
    public function onlineCount()
    {
        $sseService = SseService::getInstance();
        return $this->success($sseService->getOnlineUserCount());
    }
}
