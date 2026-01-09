<?php

declare(strict_types=1);

namespace app\common\controller;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\util\IdStringify;
use app\common\web\PageResult;
use app\common\web\Result;
use app\common\web\ResultCode;

abstract class ApiController extends BaseController
{
    protected function ok(mixed $data = null): \think\Response
    {
        $payload = Result::success(IdStringify::stringify($data))->toArray();
        return json($payload);
    }

    protected function okPage(array $list, int $total): \think\Response
    {
        $pageNum = (int) $this->request->param('pageNum', 1);
        $pageSize = (int) $this->request->param('pageSize', 10);

        if ($pageNum <= 0) {
            $pageNum = 1;
        }

        if ($pageSize <= 0) {
            $pageSize = 10;
        }

        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $payload = PageResult::success(IdStringify::stringify($list), $total, $pageNum, $pageSize)->toArray();
        return json($payload);
    }

    protected function getAuthUser(): array
    {
        if (!($this->request instanceof \app\Request)) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR);
        }

        $authUser = $this->request->getAuthUser();
        return is_array($authUser) ? $authUser : [];
    }

    protected function getAuthUserId(): int
    {
        $authUser = $this->getAuthUser();
        $userId = (int) (($authUser['userId'] ?? 0));
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }
        return $userId;
    }

    protected function mergeJsonParams(): array
    {
        $params = $this->request->param();
        $json = $this->getJsonBody();
        if (!empty($json)) {
            $params = array_merge($params, $json);
        }
        return $params;
    }

    protected function getJsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '' && method_exists($this->request, 'getInput')) {
            $raw = (string) $this->request->getInput();
        }
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
