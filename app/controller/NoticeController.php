<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\NoticeService;

/**
 * 通知公告接口 /api/v1/notices
 *
 * 分页 详情 发布撤回 已读 我的通知
 */
final class NoticeController extends ApiController
{
    /**
     * 通知公告分页列表
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function page(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $authUser = $this->getAuthUser();
        [$list, $total] = (new NoticeService())->getNoticePage($userId, $this->request->param(), $authUser);
        return $this->okPage($list, $total);
    }

    /**
     * 新增通知公告
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function create(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new NoticeService())->saveNotice($userId, $data);
        return $this->ok();
    }

    /**
     * 获取通知公告表单数据
     *
     * @param int $id 公告ID
     * @return \think\Response
     */
    public function form(int $id): \think\Response
    {
        $data = (new NoticeService())->getNoticeFormData($id);
        return $this->ok($data);
    }

    /**
     * 阅读并获取通知公告详情
     *
     * @param int $id 公告ID
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function detail(int $id): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = (new NoticeService())->getNoticeDetail($userId, $id);
        return $this->ok($data);
    }

    /**
     * 修改通知公告
     *
     * @param int $id 公告ID
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function update(int $id): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new NoticeService())->updateNotice($userId, $id, $data);
        return $this->ok();
    }

    /**
     * 发布通知公告
     *
     * @param int $id 公告ID
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function publish(int $id): \think\Response
    {
        $userId = $this->getAuthUserId();
        (new NoticeService())->publishNotice($userId, $id);
        return $this->ok();
    }

    /**
     * 撤回通知公告
     *
     * @param int $id 公告ID
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function revoke(int $id): \think\Response
    {
        $userId = $this->getAuthUserId();
        (new NoticeService())->revokeNotice($userId, $id);
        return $this->ok();
    }

    /**
     * 删除通知公告（批量）
     *
     * @param string $ids 逗号分隔ID列表
     * @return \think\Response
     */
    public function delete(string $ids): \think\Response
    {
        (new NoticeService())->deleteNotices($ids);
        return $this->ok();
    }

    /**
     * 全部标记为已读
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function readAll(): \think\Response
    {
        $userId = $this->getAuthUserId();
        (new NoticeService())->readAll($userId);
        return $this->ok();
    }

    /**
     * 我的通知分页列表
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function my(): \think\Response
    {
        $userId = $this->getAuthUserId();
        [$list, $total] = (new NoticeService())->getMyNoticePage($userId, $this->request->param());
        return $this->okPage($list, $total);
    }
}
