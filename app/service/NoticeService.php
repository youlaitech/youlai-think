<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\Notice;
use think\facade\Db;

/**
 * 通知公告业务
 *
 * 公告分页 详情 已读 发布撤回 我的通知
 */
final class NoticeService
{
    /**
     * 通知公告分页列表。
     *
     * @param int   $userId
     * @param array $queryParams
     *
     * @return array
     */
    public function getNoticePage(int $userId, array $queryParams, ?array $authUser = null): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $title = trim((string) ($queryParams['title'] ?? ''));
        $publishStatus = $queryParams['publishStatus'] ?? null;

        $q = Db::name('sys_notice')
            ->alias('n')
            ->leftJoin('sys_user pu', 'n.publisher_id = pu.id')
            ->leftJoin('sys_user cu', 'n.create_by = cu.id')
            ->leftJoin('sys_user_notice un', 'un.notice_id = n.id AND un.user_id = ' . (int) $userId . ' AND un.is_deleted = 0')
            ->where('n.is_deleted', 0);

        // 数据权限：1-所有数据 2-部门及子部门 3-本部门 4-本人
        if (is_array($authUser)) {
            $scope = (int) ($authUser['dataScope'] ?? 0);
            $authUserId = (int) ($authUser['userId'] ?? 0);
            $authDeptId = $authUser['deptId'] ?? null;
            $authDeptId = $authDeptId === null || $authDeptId === '' ? null : (int) $authDeptId;

            if ($scope === 4 && $authUserId > 0) {
                $q = $q->where('n.create_by', $authUserId);
            } elseif (($scope === 2 || $scope === 3)) {
                if ($authDeptId === null || $authDeptId <= 0) {
                    $q = $q->where('n.id', -1);
                } elseif ($scope === 3) {
                    $q = $q->where('cu.dept_id', $authDeptId);
                } else {
                    $deptIds = Db::name('sys_dept')
                        ->where('is_deleted', 0)
                        ->where(function ($sub) use ($authDeptId) {
                            $sub->where('id', $authDeptId)
                                ->whereOrRaw("FIND_IN_SET(?, tree_path)", [$authDeptId]);
                        })
                        ->column('id');

                    $deptIds = array_values(array_unique(array_filter(array_map('intval', $deptIds), fn($v) => $v > 0)));
                    if (!empty($deptIds)) {
                        $q = $q->whereIn('cu.dept_id', $deptIds);
                    } else {
                        $q = $q->where('cu.dept_id', $authDeptId);
                    }
                }
            }
        }

        if ($title !== '') {
            $q = $q->whereLike('n.title', '%' . $title . '%');
        }

        if ($publishStatus !== null && $publishStatus !== '') {
            $dbStatus = $this->toDbPublishStatus((int) $publishStatus);
            $q = $q->where('n.publish_status', $dbStatus);
        }

        $total = (int) (clone $q)->count('n.id');

        $rows = $q
            ->field('n.id,n.title,n.publish_status,n.type,n.level,n.target_type,n.publish_time,n.revoke_time,n.create_time,pu.nickname as publisher_name,un.is_read')
            ->order('n.id', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'title' => (string) ($r['title'] ?? ''),
                'publishStatus' => $this->fromDbPublishStatus((int) ($r['publish_status'] ?? 0)),
                'type' => isset($r['type']) ? (int) $r['type'] : 0,
                'publisherName' => $r['publisher_name'] ?? null,
                'level' => (string) ($r['level'] ?? ''),
                'publishTime' => $r['publish_time'] ?? null,
                'isRead' => isset($r['is_read']) ? (int) $r['is_read'] : 0,
                'targetType' => isset($r['target_type']) ? (int) $r['target_type'] : null,
                'createTime' => $r['create_time'] ?? null,
                'revokeTime' => $r['revoke_time'] ?? null,
            ];
        }

        return [$list, $total];
    }

    /**
     * 获取通知公告表单数据。
     *
     * @param int $id
     *
     * @return array
     */
    public function getNoticeFormData(int $id): array
    {
        $notice = Notice::where('id', $id)->where('is_deleted', 0)->find();
        if ($notice === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '通知公告不存在');
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
            'publishStatus' => $this->fromDbPublishStatus((int) ($n['publish_status'] ?? 0)),
            'targetType' => isset($n['target_type']) ? (int) $n['target_type'] : null,
            // 关键点：前端指定用户是多选（数组），数据库存逗号分隔字符串。
            'targetUserIds' => $targetUserIds,
        ];
    }

    /**
     * 新增通知公告。
     *
     * @param int   $userId
     * @param array $data
     *
     * @return bool
     */
    public function saveNotice(int $userId, array $data): bool
    {
        $title = trim((string) ($data['title'] ?? ''));
        $content = (string) ($data['content'] ?? '');
        $type = (int) ($data['type'] ?? 0);
        $level = (string) ($data['level'] ?? 'L');
        $targetType = (int) ($data['targetType'] ?? 1);
        $targetUserIds = $this->normalizeTargetUserIds($data['targetUserIds'] ?? null);

        if ($title === '' || trim(strip_tags($content)) === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        if ($targetType === 2 && empty($targetUserIds)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '推送指定用户不能为空');
        }

        $now = date('Y-m-d H:i:s');
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
            'create_time' => $now,
            'update_by' => $userId,
            'update_time' => $now,
            'is_deleted' => 0,
        ]);

        return true;
    }

    /**
     * 修改通知公告。
     *
     * @param int   $userId
     * @param int   $id
     * @param array $data
     *
     * @return bool
     */
    public function updateNotice(int $userId, int $id, array $data): bool
    {
        $notice = Notice::where('id', $id)->where('is_deleted', 0)->find();
        if ($notice === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '通知公告不存在');
        }

        $title = trim((string) ($data['title'] ?? ''));
        $content = (string) ($data['content'] ?? '');
        $type = (int) ($data['type'] ?? 0);
        $level = (string) ($data['level'] ?? 'L');
        $targetType = (int) ($data['targetType'] ?? 1);
        $targetUserIds = $this->normalizeTargetUserIds($data['targetUserIds'] ?? null);

        if ($title === '' || trim(strip_tags($content)) === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        if ($targetType === 2 && empty($targetUserIds)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '推送指定用户不能为空');
        }

        $notice->save([
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'level' => $level,
            'target_type' => $targetType,
            'target_user_ids' => empty($targetUserIds) ? null : implode(',', $targetUserIds),
            'update_by' => $userId,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * 发布通知公告并生成用户通知。
     *
     * @param int $userId
     * @param int $id
     *
     * @return bool
     */
    public function publishNotice(int $userId, int $id): bool
    {
        $notice = Db::name('sys_notice')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$notice) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '通知公告不存在');
        }

        if ((int) ($notice['publish_status'] ?? 0) === 1) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '通知公告已发布');
        }

        $targetType = (int) ($notice['target_type'] ?? 1);
        $targetUserIds = (string) ($notice['target_user_ids'] ?? '');
        if ($targetType === 2 && trim($targetUserIds) === '') {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '推送指定用户不能为空');
        }

        $now = date('Y-m-d H:i:s');

        // 关键点：发布需要写入用户通知表（sys_user_notice），用于“我的通知/已读状态”。
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

        return true;
    }

    /**
     * 撤回通知公告并清理用户通知。
     *
     * @param int $userId
     * @param int $id
     *
     * @return bool
     */
    public function revokeNotice(int $userId, int $id): bool
    {
        $notice = Db::name('sys_notice')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$notice) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '通知公告不存在');
        }

        if ((int) ($notice['publish_status'] ?? 0) !== 1) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '通知公告未发布或已撤回');
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

        return true;
    }

    /**
     * 删除通知公告（批量）。
     *
     * @param string $ids
     *
     * @return bool
     */
    public function deleteNotices(string $ids): bool
    {
        $ids = trim($ids);
        if ($ids === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $ids)), fn($v) => $v !== ''));
        $idList = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p)) {
                throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
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
     * 全部标记为已读。
     *
     * @param int $userId
     *
     * @return bool
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
     * 阅读并获取通知公告详情。
     *
     * @param int $userId
     * @param int $id
     *
     * @return array
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
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '通知公告不存在');
        }

        // 关键点：阅读详情时，更新用户通知已读状态。
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
            'publisherName' => $row['publisher_name'] ?? null,
            'level' => $row['level'] ?? null,
            'publishStatus' => $this->fromDbPublishStatus((int) ($row['publish_status'] ?? 0)),
            'publishTime' => $row['publish_time'] ?? null,
        ];
    }

    /**
     * 我的通知分页列表。
     *
     * @param int   $userId
     * @param array $queryParams
     *
     * @return array
     */
    public function getMyNoticePage(int $userId, array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $title = trim((string) ($queryParams['title'] ?? ''));
        $isRead = $queryParams['isRead'] ?? null;

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

        $total = (int) (clone $q)->count('un.id');

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
                'publisherName' => $r['publisher_name'] ?? null,
                'publishTime' => $r['publish_time'] ?? null,
                'isRead' => isset($r['is_read']) ? (int) $r['is_read'] : 0,
            ];
        }

        return [$list, $total];
    }

    /**
     * 规范化 targetUserIds（支持字符串/数组）。
     *
     * @param mixed $value
     *
     * @return array
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
     * 数据库发布状态转前端状态。
     *
     * @param int $dbStatus
     *
     * @return int
     */
    private function fromDbPublishStatus(int $dbStatus): int
    {
        // 数据库：0未发布 / 1已发布 / -1已撤回
        // 前端：0草稿 / 1已发布 / 2已撤回
        return $dbStatus === -1 ? 2 : $dbStatus;
    }

    /**
     * 前端发布状态转数据库状态。
     *
     * @param int $status
     *
     * @return int
     */
    private function toDbPublishStatus(int $status): int
    {
        return $status === 2 ? -1 : $status;
    }
}
