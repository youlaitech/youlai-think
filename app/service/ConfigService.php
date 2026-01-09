<?php

declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\redis\RedisClient;
use app\common\web\ResultCode;
use app\model\Config;
use think\facade\Db;

/**
 * 系统配置业务
 *
 * 配置分页 表单 增删改 缓存
 */
final class ConfigService
{
    private const CACHE_KEY = 'system:config';

    /**
     * 系统配置分页列表。
     */
    public function page(array $queryParams): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $keywords = trim((string) ($queryParams['keywords'] ?? ''));

        $q = Db::name('sys_config')->where('is_deleted', 0);
        if ($keywords !== '') {
            $kw = '%' . $keywords . '%';
            $q = $q->whereLike('config_key|config_name', $kw);
        }

        $total = (int) (clone $q)->count('id');

        $rows = $q
            ->field('id,config_name,config_key,config_value,remark')
            ->order('id', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id' => (string) ($r['id'] ?? ''),
                'configName' => $r['config_name'] ?? null,
                'configKey' => $r['config_key'] ?? null,
                'configValue' => $r['config_value'] ?? null,
                'remark' => $r['remark'] ?? null,
            ];
        }

        return [$list, $total];
    }

    /**
     * 获取系统配置表单数据。
     */
    public function getConfigFormData(int $id): array
    {
        $row = Config::where('id', $id)->where('is_deleted', 0)->find();
        if ($row === null) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '系统配置不存在');
        }

        $r = $row->toArray();
        return [
            'id' => (string) ($r['id'] ?? ''),
            'configName' => $r['config_name'] ?? null,
            'configKey' => $r['config_key'] ?? null,
            'configValue' => $r['config_value'] ?? null,
            'remark' => $r['remark'] ?? null,
        ];
    }

    /**
     * 新增系统配置。
     */
    public function saveConfig(int $userId, array $data): bool
    {
        $configName = trim((string) ($data['configName'] ?? ''));
        $configKey = trim((string) ($data['configKey'] ?? ''));
        $configValue = (string) ($data['configValue'] ?? '');
        $remark = $data['remark'] ?? null;

        if ($configName === '' || $configKey === '' || $configValue === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $exists = Db::name('sys_config')
            ->where('is_deleted', 0)
            ->where('config_key', $configKey)
            ->count();
        if ($exists > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '配置键已存在');
        }

        $now = date('Y-m-d H:i:s');
        Db::name('sys_config')->insert([
            'config_name' => $configName,
            'config_key' => $configKey,
            'config_value' => $configValue,
            'remark' => $remark,
            'create_time' => $now,
            'create_by' => $userId,
            'update_time' => $now,
            'update_by' => $userId,
            'is_deleted' => 0,
        ]);

        return true;
    }

    /**
     * 修改系统配置。
     */
    public function updateConfig(int $userId, int $id, array $data): bool
    {
        $row = Db::name('sys_config')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '系统配置不存在');
        }

        $configName = trim((string) ($data['configName'] ?? ''));
        $configKey = trim((string) ($data['configKey'] ?? ''));
        $configValue = (string) ($data['configValue'] ?? '');
        $remark = $data['remark'] ?? null;

        if ($configName === '' || $configKey === '' || $configValue === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $exists = Db::name('sys_config')
            ->where('is_deleted', 0)
            ->where('config_key', $configKey)
            ->where('id', '<>', $id)
            ->count();
        if ($exists > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '配置键已存在');
        }

        Db::name('sys_config')->where('id', $id)->update([
            'config_name' => $configName,
            'config_key' => $configKey,
            'config_value' => $configValue,
            'remark' => $remark,
            'update_time' => date('Y-m-d H:i:s'),
            'update_by' => $userId,
        ]);

        return true;
    }

    /**
     * 删除系统配置。
     */
    public function deleteConfig(int $userId, int $id): bool
    {
        $row = Db::name('sys_config')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '系统配置不存在');
        }

        Db::name('sys_config')->where('id', $id)->update([
            'is_deleted' => 1,
            'update_time' => date('Y-m-d H:i:s'),
            'update_by' => $userId,
        ]);

        return true;
    }

    /**
     * 刷新系统配置缓存。
     */
    public function refreshCache(): bool
    {
        $rows = Db::name('sys_config')
            ->where('is_deleted', 0)
            ->field('config_key,config_value')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $r) {
            $k = (string) ($r['config_key'] ?? '');
            if ($k === '') {
                continue;
            }
            $map[$k] = (string) ($r['config_value'] ?? '');
        }

        $redis = RedisClient::get();
        $redis->del([self::CACHE_KEY]);
        if (!empty($map)) {
            $redis->hmset(self::CACHE_KEY, $map);
        }

        return true;
    }

    /**
     * 获取系统配置（优先读缓存）。
     */
    public function getSystemConfig(string $key): mixed
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        $redis = RedisClient::get();
        $val = $redis->hget(self::CACHE_KEY, $key);
        if ($val !== null) {
            return $val;
        }

        $row = Db::name('sys_config')
            ->where('is_deleted', 0)
            ->where('config_key', $key)
            ->value('config_value');

        return $row;
    }
}
