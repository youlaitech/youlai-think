<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\NoticeService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="08.通知公告")
 */
final class NoticeController extends ApiController
{
    /**
     * 通知公告分页列表
     *
     * @OA\Get(
     *     path="/api/v1/notices",
     *     summary="通知公告分页列表",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Parameter(name="keywords", in="query", description="关键字", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function page(): \think\Response
    {
        // 需要用户身份与数据权限
        $userId = $this->getAuthUserId();
        $authUser = $this->getAuthUser();
        [$list, $total] = (new NoticeService())->getNoticePage($userId, $this->request->param(), $authUser);
        return $this->okPage($list, $total);
    }

    /**
     * 新增通知公告
     *
     * @OA\Post(
     *     path="/api/v1/notices",
     *     summary="新增通知公告",
     *     tags={"08.通知公告"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function create(): \think\Response
    {
        $userId = $this->getAuthUserId();
        // 统一读取 body 参数
        $data = $this->mergeJsonParams();
        (new NoticeService())->saveNotice($userId, $data);
        return $this->ok();
    }

    /**
     * 获取通知公告表单数据
     *
     * @OA\Get(
     *     path="/api/v1/notices/{id}/form",
     *     summary="获取通知公告表单数据",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="id", in="path", description="通知公告ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Get(
     *     path="/api/v1/notices/{id}/detail",
     *     summary="阅读获取通知公告详情",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="id", in="path", description="通知公告ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Put(
     *     path="/api/v1/notices/{id}",
     *     summary="修改通知公告",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="id", in="path", description="通知公告ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 公告ID
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function update(int $id): \think\Response
    {
        $userId = $this->getAuthUserId();
        // 统一读取 body 参数
        $data = $this->mergeJsonParams();
        (new NoticeService())->updateNotice($userId, $id, $data);
        return $this->ok();
    }

    /**
     * 发布通知公告
     *
     * @OA\Put(
     *     path="/api/v1/notices/{id}/publish",
     *     summary="发布通知公告",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="id", in="path", description="通知公告ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Put(
     *     path="/api/v1/notices/{id}/revoke",
     *     summary="撤回通知公告",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="id", in="path", description="通知公告ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Delete(
     *     path="/api/v1/notices/{ids}",
     *     summary="删除通知公告",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="ids", in="path", description="通知公告ID，多个以英文逗号(,)分割", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
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
     * @OA\Put(
     *     path="/api/v1/notices/read-all",
     *     summary="全部已读",
     *     tags={"08.通知公告"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function readAll(): \think\Response
    {
        $userId = $this->getAuthUserId();
        // 批量标记已读
        (new NoticeService())->readAll($userId);
        return $this->ok();
    }

    /**
     * 我的通知分页列表
     *
     * @OA\Get(
     *     path="/api/v1/notices/my",
     *     summary="获取我的通知公告分页列表",
     *     tags={"08.通知公告"},
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function my(): \think\Response
    {
        $userId = $this->getAuthUserId();
        // 仅查询我的通知列表
        [$list, $total] = (new NoticeService())->getMyNoticePage($userId, $this->request->param());
        return $this->okPage($list, $total);
    }
}
