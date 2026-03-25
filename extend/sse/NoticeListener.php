<?php declare(strict_types=1);

namespace extend\sse;

use app\common\constants\NoticeEvents;

class NoticeListener
{
    public function __construct(private SseService $sseService) {}

    public function onPublished(array $notice): void
    {
        $this->sseService->broadcast('notice', $notice);
    }

    public function onRevoked(int $noticeId): void
    {
        $this->sseService->broadcast('notice-revoke', ['id' => $noticeId]);
    }

    public static function mappings(): array
    {
        return [
            NoticeEvents::PUBLISHED => 'onPublished',
            NoticeEvents::REVOKED   => 'onRevoked',
        ];
    }
}
