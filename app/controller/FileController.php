<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\service\FileService;

final class FileController extends ApiController
{
    public function upload(): \think\Response
    {
        $file = $this->request->file('file');
        if ($file === null) {
            throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION);
        }

        $info = (new FileService())->uploadFile($file, $this->request);
        return $this->ok($info);
    }

    public function delete(): \think\Response
    {
        $filePath = (string) $this->request->param('filePath', '');
        if ($filePath === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $result = (new FileService())->deleteFile($filePath);
        return json(Result::judge($result)->toArray());
    }
}
