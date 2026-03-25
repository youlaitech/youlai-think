<?php declare(strict_types=1);

namespace app\common\constants;

/**
 * 通知事件常量定义
 */
class NoticeEvents
{
    // 发布通知事件
    public const PUBLISHED = 'notice.published';

    // 撤回通知事件
    public const REVOKED = 'notice.revoked';
}
