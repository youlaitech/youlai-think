<?php declare(strict_types=1);

namespace app\file\controller;

use app\controller\BaseController;
use app\common\exception\BusinessException;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\file\service\FileService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="10.文件接口")
 */
final class FileController extends BaseController
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
        $file = $this->request->file('file');
        if ($file === null) {
            throw new BusinessException('上传文件不能为空');
        }

        $info = $this->service(FileService::class)->uploadFile($file, $this->request);
        return $this->success($info);
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
            throw new BusinessException('文件路径不能为空');
        }

        $result = $this->service(FileService::class)->deleteFile($filePath);
        return $result ? $this->success() : $this->fail('A0710');
    }
}
