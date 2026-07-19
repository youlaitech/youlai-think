<?php declare(strict_types=1);

namespace extend\sse;

use app\common\constants\NoticeEvents;

/**
 * 通知公告事件监听器，转发为 SSE 广播
 */
class NoticeListener
{
    public function __construct(private SseService $sseService) {}

    public function onPublished(array $notice): void
    {
        $this->sseService->broadcast(SseTopics::NOTICE, $notice);
    }

    public function onRevoked(int $noticeId): void
    {
        $this->sseService->broadcast(SseTopics::NOTICE_REVOKE, ['id' => $noticeId]);
    }

    public static function mappings(): array
    {
        return [
            NoticeEvents::PUBLISHED => 'onPublished',
            NoticeEvents::REVOKED   => 'onRevoked',
        ];
    }
}
