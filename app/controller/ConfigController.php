<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\ConfigService;

/**
 * 系统配置接口 /api/v1/configs
 *
 * 配置分页 表单 增删改 刷新缓存
 */
final class ConfigController extends ApiController
{
    /**
     * 系统配置分页列表
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
     * @return \think\Response
     */
    public function refresh(): \think\Response
    {
        (new ConfigService())->refreshCache();
        return $this->ok();
    }
}
