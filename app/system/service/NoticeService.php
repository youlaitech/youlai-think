<?php declare(strict_types=1);

namespace app\system\service;

use app\common\exception\BusinessException;
use app\common\util\PageUtil;
use app\system\model\Notice;
use extend\sse\SseService;
use think\facade\Db;

final class NoticeService
{
    /**
     * 通知公告分页列表
     */
    public function getNoticePage(int $userId, array $queryParams, ?array $authUser = null): array
    {
        [$pageNum, $pageSize] = PageUtil::resolve($queryParams);

        $title = trim((string) ($queryParams['title'] ?? ''));
        $publishStatus = $queryParams['publish_status'] ?? null;

        // 关联发布人、创建人、阅读状态
        $q = Db::name('sys_notice')
            ->alias('n')
            ->leftJoin('sys_user pu', 'n.publisher_id = pu.id')
            ->leftJoin('sys_user cu', 'n.create_by = cu.id')
            ->leftJoin('sys_user_notice un', 'un.notice_id = n.id AND un.user_id = ' . (int) $userId . ' AND un.is_deleted = 0')
            ->where('n.is_deleted', 0);

        // 数据权限过滤（支持多角色并集策略）
        if (is_array($authUser)) {
            $dataPermissionService = app(DataPermissionService::class);
            $q = $dataPermissionService->apply($q, 'cu.dept_id', 'n.create_by', $authUser);
        }

        if ($title !== '') {
            $q = $q->whereLike('n.title', '%' . $title . '%');
        }

        // 前端状态转数据库状态
        if ($publishStatus !== null && $publishStatus !== '') {
            $dbStatus = $this->toDbPublishStatus((int) $publishStatus);
            $q = $q->where('n.publish_status', $dbStatus);
        }

        $total = (int) $q->count('n.id');

        $rows = $q
            ->field('n.id,n.title,n.publish_status,n.type,n.level,n.target_type,n.publish_time,n.revoke_time,n.create_time,pu.nickname as publisher_name,un.is_read')
            ->order('n.publish_time', 'desc')
            ->order('n.create_time', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'title' => (string) ($r['title'] ?? ''),
                'publish_status' => $this->fromDbPublishStatus((int) ($r['publish_status'] ?? 0)),
                'type' => isset($r['type']) ? (int) $r['type'] : 0,
                'publisher_name' => $r['publisher_name'] ?? null,
                'level' => (string) ($r['level'] ?? ''),
                'publish_time' => $r['publish_time'] ?? null,
                'is_read' => isset($r['is_read']) ? (int) $r['is_read'] : 0,
                'target_type' => isset($r['target_type']) ? (int) $r['target_type'] : null,
                'create_time' => $r['create_time'] ?? null,
                'revoke_time' => $r['revoke_time'] ?? null,
            ];
        }

        return [$list, $total];
    }

    /**
     * 获取通知公告表单数据
     */
    public function getNoticeFormData(int $id): array
    {
        $notice = Notice::find($id);
        if ($notice === null) {
            throw new BusinessException('通知公告不存在');
        }

        $n = $notice->toArray();

        $targetUserIds = [];
        $raw = (string) ($n['target_user_ids'] ?? '');
        if ($raw !== '') {
            $targetUserIds = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));
        }

        return [
            'id' => (string) ($n['id'] ?? ''),
            'title' => $n['title'] ?? null,
            'content' => $n['content'] ?? null,
            'type' => isset($n['type']) ? (int) $n['type'] : null,
            'level' => $n['level'] ?? null,
            'publish_status' => $this->fromDbPublishStatus((int) ($n['publish_status'] ?? 0)),
            'target_type' => isset($n['target_type']) ? (int) ($n['target_type']) : null,
            'target_user_ids' => $targetUserIds,
        ];
    }

    /**
     * 新增通知公告
     */
    public function saveNotice(int $userId, array $data): bool
    {
        $title = trim((string) ($data['title'] ?? ''));
        $content = (string) ($data['content'] ?? '');
        $type = (int) ($data['type'] ?? 0);
        $level = (string) ($data['level'] ?? 'L');
        $targetType = (int) ($data['target_type'] ?? 1);
        // 支持数组/逗号分隔字符串
        $targetUserIds = $this->normalizeTargetUserIds($data['target_users'] ?? null);

        if ($title === '' || trim(strip_tags($content)) === '') {
            throw new BusinessException('标题或内容不能为空');
        }

        if ($targetType === 2 && empty($targetUserIds)) {
            throw new BusinessException('推送指定用户不能为空');
        }

        $notice = new Notice();
        $notice->save([
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'level' => $level,
            'target_type' => $targetType,
            'target_user_ids' => empty($targetUserIds) ? null : implode(',', $targetUserIds),
            'publish_status' => 0,
            'create_by' => $userId,
            'update_by' => $userId,
            'is_deleted' => 0,
        ]);

        return true;
    }

    /**
     * 修改通知公告
     */
    public function updateNotice(int $userId, int $id, array $data): bool
    {
        $notice = Notice::find($id);
        if ($notice === null) {
            throw new BusinessException('通知公告不存在');
        }

        $title = trim((string) ($data['title'] ?? ''));
        $content = (string) ($data['content'] ?? '');
        $type = (int) ($data['type'] ?? 0);
        $level = (string) ($data['level'] ?? 'L');
        $targetType = (int) ($data['target_type'] ?? 1);
        // 支持数组/逗号分隔字符串
        $targetUserIds = $this->normalizeTargetUserIds($data['target_users'] ?? null);

        if ($title === '' || trim(strip_tags($content)) === '') {
            throw new BusinessException('标题或内容不能为空');
        }

        if ($targetType === 2 && empty($targetUserIds)) {
            throw new BusinessException('推送指定用户不能为空');
        }

        $notice->save([
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'level' => $level,
            'target_type' => $targetType,
            'target_user_ids' => empty($targetUserIds) ? null : implode(',', $targetUserIds),
            'update_by' => $userId,
        ]);

        return true;
    }

    /**
     * 发布通知公告
     */
    public function publishNotice(int $userId, int $id): bool
    {
        $notice = Db::name('sys_notice')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$notice) {
            throw new BusinessException('通知公告不存在');
        }

        if ((int) ($notice['publish_status'] ?? 0) === 1) {
            throw new BusinessException('通知公告已发布');
        }

        $targetType = (int) ($notice['target_type'] ?? 1);
        // 指定用户模式需要目标用户列表
        $targetUserIds = (string) ($notice['target_user_ids'] ?? '');
        if ($targetType === 2 && trim($targetUserIds) === '') {
            throw new BusinessException('推送指定用户不能为空');
        }

        $now = date('Y-m-d H:i:s');

        Db::transaction(function () use ($userId, $id, $targetType, $targetUserIds, $now) {
            Db::name('sys_notice')->where('id', $id)->update([
                'publish_status' => 1,
                'publisher_id' => $userId,
                'publish_time' => $now,
                'revoke_time' => null,
                'update_by' => $userId,
                'update_time' => $now,
            ]);

            Db::name('sys_user_notice')->where('notice_id', $id)->update(['is_deleted' => 1, 'update_time' => $now]);

            // 仅给启用用户生成通知
            $userQuery = Db::name('sys_user')->where('is_deleted', 0)->where('status', 1);
            if ($targetType === 2) {
                $ids = array_values(array_filter(array_map('trim', explode(',', $targetUserIds)), fn($v) => $v !== ''));
                $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
                if (!empty($ids)) {
                    $userQuery = $userQuery->whereIn('id', $ids);
                }
            }

            $users = $userQuery->field('id')->select()->toArray();
            if (empty($users)) {
                return;
            }

            $rows = [];
            foreach ($users as $u) {
                $uid = (int) ($u['id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $rows[] = [
                    'notice_id' => $id,
                    'user_id' => $uid,
                    'is_read' => 0,
                    'read_time' => null,
                    'create_time' => $now,
                    'update_time' => $now,
                    'is_deleted' => 0,
                ];
            }

            if (!empty($rows)) {
                Db::name('sys_user_notice')->insertAll($rows);
            }
        });

        // SSE通知在线用户
        $this->broadcastNoticePublished((int) $notice['id'], $notice);

        return true;
    }

    /**
     * 撤回通知公告
     */
    public function revokeNotice(int $userId, int $id): bool
    {
        $notice = Db::name('sys_notice')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$notice) {
            throw new BusinessException('通知公告不存在');
        }

        if ((int) ($notice['publish_status'] ?? 0) !== 1) {
            throw new BusinessException('通知公告未发布或已撤回');
        }

        $now = date('Y-m-d H:i:s');

        Db::transaction(function () use ($userId, $id, $now) {
            Db::name('sys_notice')->where('id', $id)->update([
                'publish_status' => -1,
                'revoke_time' => $now,
                'update_by' => $userId,
                'update_time' => $now,
            ]);

            Db::name('sys_user_notice')->where('notice_id', $id)->update(['is_deleted' => 1, 'update_time' => $now]);
        });

        // SSE通知前端移除该通知
        $sseService = SseService::getInstance();
        $onlineUsers = $sseService->getOnlineUsers();
        foreach ($onlineUsers as $u) {
            $sseService->sendToUser($u->username, 'notice-revoke', ['id' => $id]);
        }

        return true;
    }

    /**
     * 批量删除通知公告
     */
    public function deleteNotices(string $ids): bool
    {
        $ids = trim($ids);
        if ($ids === '') {
            throw new BusinessException('ID不能为空');
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $ids)), fn($v) => $v !== ''));
        $idList = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p)) {
                throw new BusinessException('ID格式不正确');
            }
            $idList[] = (int) $p;
        }

        $now = date('Y-m-d H:i:s');

        Db::transaction(function () use ($idList, $now) {
            Db::name('sys_notice')->whereIn('id', $idList)->update([
                'is_deleted' => 1,
                'update_time' => $now,
            ]);

            Db::name('sys_user_notice')->whereIn('notice_id', $idList)->update([
                'is_deleted' => 1,
                'update_time' => $now,
            ]);
        });

        return true;
    }

    /**
     * 全部标记已读
     */
    public function readAll(int $userId): bool
    {
        $now = date('Y-m-d H:i:s');
        Db::name('sys_user_notice')
            ->where('user_id', $userId)
            ->where('is_deleted', 0)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_time' => $now,
                'update_time' => $now,
            ]);

        return true;
    }

    /**
     * 阅读通知详情（同时标记已读）
     */
    public function getNoticeDetail(int $userId, int $id): array
    {
        $row = Db::name('sys_notice')
            ->alias('n')
            ->leftJoin('sys_user u', 'n.publisher_id = u.id')
            ->where('n.id', $id)
            ->where('n.is_deleted', 0)
            ->field('n.id,n.title,n.content,n.type,n.level,n.publish_status,n.publish_time,u.nickname as publisher_name')
            ->find();

        if (!$row) {
            throw new BusinessException('通知公告不存在');
        }

        // 标记已读
        $now = date('Y-m-d H:i:s');
        Db::name('sys_user_notice')
            ->where('notice_id', $id)
            ->where('user_id', $userId)
            ->where('is_deleted', 0)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_time' => $now, 'update_time' => $now]);

        return [
            'id' => (string) ($row['id'] ?? ''),
            'title' => $row['title'] ?? null,
            'content' => $row['content'] ?? null,
            'type' => isset($row['type']) ? (int) $row['type'] : null,
            'publisher_name' => $row['publisher_name'] ?? null,
            'level' => $row['level'] ?? null,
            'publish_status' => $this->fromDbPublishStatus((int) ($row['publish_status'] ?? 0)),
            'publish_time' => $row['publish_time'] ?? null,
        ];
    }

    /**
     * 我的通知分页列表
     */
    public function getMyNoticePage(int $userId, array $queryParams): array
    {
        [$pageNum, $pageSize] = PageUtil::resolve($queryParams);

        $title = trim((string) ($queryParams['title'] ?? ''));
        $isRead = $queryParams['is_read'] ?? null;

        $q = Db::name('sys_user_notice')
            ->alias('un')
            ->join('sys_notice n', 'un.notice_id = n.id')
            ->leftJoin('sys_user u', 'n.publisher_id = u.id')
            ->where('un.user_id', $userId)
            ->where('un.is_deleted', 0)
            ->where('n.is_deleted', 0)
            ->where('n.publish_status', 1);

        if ($title !== '') {
            $q = $q->whereLike('n.title', '%' . $title . '%');
        }

        if ($isRead !== null && $isRead !== '') {
            $q = $q->where('un.is_read', (int) $isRead);
        }

        $total = (int) $q->count('un.id');

        $rows = $q
            ->field('n.id,n.title,n.type,n.level,u.nickname as publisher_name,n.publish_time,un.is_read')
            ->order('n.publish_time', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'title' => $r['title'] ?? null,
                'type' => isset($r['type']) ? (int) $r['type'] : null,
                'level' => $r['level'] ?? null,
                'publisher_name' => $r['publisher_name'] ?? null,
                'publish_time' => $r['publish_time'] ?? null,
                'is_read' => isset($r['is_read']) ? (int) $r['is_read'] : 0,
            ];
        }

        return [$list, $total];
    }

    /**
     * 把 targetUserIds 转成数组（兼容字符串逗号分隔）
     */
    private function normalizeTargetUserIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }
            return array_values(array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== ''));
        }

        if (is_array($value)) {
            $ids = [];
            foreach ($value as $v) {
                $v = trim((string) $v);
                if ($v !== '') {
                    $ids[] = $v;
                }
            }
            return array_values(array_unique($ids));
        }

        return [];
    }

    /**
     * 数据库发布状态 → 前端状态
     * 数据库: 0未发布 / 1已发布 / -1已撤回
     * 前端:   0草稿  / 1已发布 /  2已撤回
     */
    private function fromDbPublishStatus(int $dbStatus): int
    {
        return $dbStatus === -1 ? 2 : $dbStatus;
    }

    /**
     * 前端发布状态 → 数据库状态
     */
    private function toDbPublishStatus(int $status): int
    {
        return $status === 2 ? -1 : $status;
    }

    /**
     * SSE广播通知发布
     */
    private function broadcastNoticePublished(int $noticeId, array $notice): void
    {
        $sseService = SseService::getInstance();
        $noticeData = [
            'id' => (string) $noticeId,
            'title' => $notice['title'] ?? null,
            'type' => $notice['type'] ?? null,
            'level' => $notice['level'] ?? null,
            'publishStatus' => 1,
            'publishTime' => $notice['publish_time'] ?? null,
        ];

        $onlineUsers = $sseService->getOnlineUsers();
        foreach ($onlineUsers as $u) {
            $sseService->sendToUser($u->username, 'notice', $noticeData);
        }
    }
}
