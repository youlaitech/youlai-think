<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\CodegenService;

/**
 * 代码生成接口
 */
final class CodegenController extends ApiController
{
    /**
     * 数据表分页
     */
    public function tablePage(): \think\Response
    {
        [$list, $total] = (new CodegenService())->getTablePage($this->request->param());
        return $this->okPage($list, $total);
    }

    /**
     * 获取生成配置
     */
    public function getConfig(string $tableName): \think\Response
    {
        $data = (new CodegenService())->getGenConfigFormData($tableName);
        return $this->ok($data);
    }

    /**
     * 保存生成配置
     */
    public function saveConfig(string $tableName): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new CodegenService())->saveGenConfig($tableName, $data);
        return $this->ok();
    }

    /**
     * 删除生成配置
     */
    public function deleteConfig(string $tableName): \think\Response
    {
        (new CodegenService())->deleteGenConfig($tableName);
        return $this->ok();
    }

    /**
     * 预览生成代码
     */
    public function preview(string $tableName): \think\Response
    {
        $pageType = (string) $this->request->param('pageType', 'classic');
        $type = (string) $this->request->param('type', 'ts');
        $list = (new CodegenService())->getCodegenPreviewData($tableName, $pageType, $type);
        return $this->ok($list);
    }

    /**
     * 下载 zip
     */
    public function download(string $tableName): \think\Response
    {
        $pageType = (string) $this->request->param('pageType', 'classic');
        $type = (string) $this->request->param('type', 'ts');
        $tableNames = array_values(array_filter(array_map('trim', explode(',', $tableName)), fn($v) => $v !== ''));

        $ret = (new CodegenService())->downloadZip($tableNames, $pageType, $type);
        $bin = (string) ($ret['bin'] ?? '');
        $fileName = (string) ($ret['fileName'] ?? ($tableName . '.zip'));

        return response($bin, 200)
            ->header([
                'Content-Type' => 'application/zip',
                'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($fileName),
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
    }
}
