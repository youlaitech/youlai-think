<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\service\CodegenService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="11.代码生成")
 */
final class CodegenController extends ApiController
{
    /**
     * 数据表分页
     *
     * @OA\Get(
     *     path="/api/v1/codegen/table",
     *     summary="获取数据表分页列表",
     *     tags={"11.代码生成"},
     *     @OA\Parameter(name="pageNum", in="query", description="页码", required=false),
     *     @OA\Parameter(name="pageSize", in="query", description="每页数量", required=false),
     *     @OA\Parameter(name="keywords", in="query", description="关键字", required=false),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function tablePage(): \think\Response
    {
        [$list, $total] = (new CodegenService())->getTablePage($this->request->param());
        return $this->okPage($list, $total);
    }

    /**
     * 获取生成配置
     *
     * @OA\Get(
     *     path="/api/v1/codegen/{tableName}/config",
     *     summary="获取代码生成配置",
     *     tags={"11.代码生成"},
     *     @OA\Parameter(name="tableName", in="path", description="表名", required=true, example="sys_user"),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function getConfig(string $tableName): \think\Response
    {
        $data = (new CodegenService())->getGenConfigFormData($tableName);
        return $this->ok($data);
    }

    /**
     * 保存生成配置
     *
     * @OA\Post(
     *     path="/api/v1/codegen/{tableName}/config",
     *     summary="保存代码生成配置",
     *     tags={"11.代码生成"},
     *     @OA\Parameter(name="tableName", in="path", description="表名", required=true, example="sys_user"),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function saveConfig(string $tableName): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new CodegenService())->saveGenConfig($tableName, $data);
        return $this->ok();
    }

    /**
     * 删除生成配置
     *
     * @OA\Delete(
     *     path="/api/v1/codegen/{tableName}/config",
     *     summary="删除代码生成配置",
     *     tags={"11.代码生成"},
     *     @OA\Parameter(name="tableName", in="path", description="表名", required=true, example="sys_user"),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function deleteConfig(string $tableName): \think\Response
    {
        (new CodegenService())->deleteGenConfig($tableName);
        return $this->ok();
    }

    /**
     * 预览生成代码
     *
     * @OA\Get(
     *     path="/api/v1/codegen/{tableName}/preview",
     *     summary="获取预览生成代码",
     *     tags={"11.代码生成"},
     *     @OA\Parameter(name="tableName", in="path", description="表名", required=true, example="sys_user"),
     *     @OA\Parameter(name="pageType", in="query", description="页面类型", required=false, example="classic"),
     *     @OA\Parameter(name="type", in="query", description="代码类型", required=false, example="ts"),
     *     @OA\Response(response=200, description="OK")
     * )
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
     *
     * @OA\Get(
     *     path="/api/v1/codegen/{tableName}/download",
     *     summary="下载代码",
     *     tags={"11.代码生成"},
     *     @OA\Parameter(name="tableName", in="path", description="表名(多个逗号分隔)", required=true, example="sys_user"),
     *     @OA\Parameter(name="pageType", in="query", description="页面类型", required=false, example="classic"),
     *     @OA\Parameter(name="type", in="query", description="代码类型", required=false, example="ts"),
     *     @OA\Response(response=200, description="OK")
     * )
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
