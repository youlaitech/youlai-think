<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\service\FileService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="10.文件接口")
 */
final class FileController extends ApiController
{
    /**
     * @OA\Post(
     *     path="/api/v1/files",
     *     summary="文件上传",
     *     tags={"10.文件接口"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function upload(): \think\Response
    {
        // 读取上传文件字段
        $file = $this->request->file('file');
        if ($file === null) {
            throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION);
        }

        $info = (new FileService())->uploadFile($file, $this->request);
        return $this->ok($info);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/files",
     *     summary="文件删除",
     *     tags={"10.文件接口"},
     *     @OA\Parameter(name="filePath", in="query", description="文件路径", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function delete(): \think\Response
    {
        $filePath = (string) $this->request->param('filePath', '');
        if ($filePath === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        // 删除 storage 内文件
        $result = (new FileService())->deleteFile($filePath);
        return json(Result::judge($result)->toArray());
    }
}
