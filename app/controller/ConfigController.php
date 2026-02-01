<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\ConfigService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="07.系统配置")
 */
final class ConfigController extends ApiController
{
    /**
     * 系统配置分页列表
     *
     * @OA\Get(
     *     path="/api/v1/configs",
     *     summary="系统配置分页列表",
     *     tags={"07.系统配置"},
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Parameter(name="keywords", in="query", description="关键字", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function page(): \think\Response
    {
        [$list, $total] = (new ConfigService())->page($this->request->param());
        return $this->okPage($list, $total);
    }

    /**
     * 获取系统配置表单数据
     *
     * @OA\Get(
     *     path="/api/v1/configs/{id}/form",
     *     summary="获取系统配置表单数据",
     *     tags={"07.系统配置"},
     *     @OA\Parameter(name="id", in="path", description="系统配置ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 配置ID
     * @return \think\Response
     */
    public function form(int $id): \think\Response
    {
        $data = (new ConfigService())->getConfigFormData($id);
        return $this->ok($data);
    }

    /**
     * 新增系统配置
     *
     * @OA\Post(
     *     path="/api/v1/configs",
     *     summary="新增系统配置",
     *     tags={"07.系统配置"},
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
        $data = $this->mergeJsonParams();
        (new ConfigService())->saveConfig($userId, $data);
        return $this->ok();
    }

    /**
     * 修改系统配置
     *
     * @OA\Put(
     *     path="/api/v1/configs/{id}",
     *     summary="修改系统配置",
     *     tags={"07.系统配置"},
     *     @OA\Parameter(name="id", in="path", description="系统配置ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 配置ID
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function update(int $id): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new ConfigService())->updateConfig($userId, $id, $data);
        return $this->ok();
    }

    /**
     * 删除系统配置
     *
     * @OA\Delete(
     *     path="/api/v1/configs/{id}",
     *     summary="删除系统配置",
     *     tags={"07.系统配置"},
     *     @OA\Parameter(name="id", in="path", description="系统配置ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 配置ID
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function delete(int $id): \think\Response
    {
        $userId = $this->getAuthUserId();
        (new ConfigService())->deleteConfig($userId, $id);
        return $this->ok();
    }

    /**
     * 刷新系统配置缓存
     *
     * @OA\Put(
     *     path="/api/v1/configs/refresh",
     *     summary="刷新系统配置缓存",
     *     tags={"07.系统配置"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function refresh(): \think\Response
    {
        (new ConfigService())->refreshCache();
        return $this->ok();
    }
}
