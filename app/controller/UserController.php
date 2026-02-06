<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\UserService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="02.用户接口")
 */
final class UserController extends ApiController
{
    /**
     * 获取当前登录用户信息
     *
     * @OA\Get(
     *     path="/api/v1/users/me",
     *     summary="获取当前登录用户信息",
     *     tags={"02.用户接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function me(): \think\Response
    {
        $userId = $this->getAuthUserId();

        $dto = (new UserService())->getCurrentUserDto($userId);
        return $this->ok($dto);
    }

    /**
     * 用户分页列表
     *
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="用户列表",
     *     tags={"02.用户接口"},
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
        $authUser = $this->getAuthUser();
        [$list, $total] = (new UserService())->getUserPage($this->request->param(), $authUser);
        return $this->okPage($list, $total);
    }

    /**
     * 获取用户表单数据
     *
     * @OA\Get(
     *     path="/api/v1/users/{userId}/form",
     *     summary="获取用户表单数据",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="userId", in="path", description="用户ID", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $userId 用户ID
     * @return \think\Response
     */
    public function form(int $userId): \think\Response
    {
        $data = (new UserService())->getUserFormData($userId);
        return $this->ok($data);
    }

    /**
     * 新增用户
     *
     * @OA\Post(
     *     path="/api/v1/users",
     *     summary="新增用户",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function create(): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new UserService())->saveUser($data);
        return $this->ok();
    }

    /**
     * 修改用户
     *
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     summary="修改用户",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="id", in="path", description="用户ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 用户ID
     * @return \think\Response
     */
    public function update(int $id): \think\Response
    {
        $data = $this->mergeJsonParams();
        (new UserService())->updateUser($id, $data);
        return $this->ok();
    }

    /**
     * 删除用户（批量）
     *
     * @OA\Delete(
     *     path="/api/v1/users/{ids}",
     *     summary="删除用户",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="ids", in="path", description="用户ID，多个以英文逗号(,)分割", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param string $ids 逗号分隔ID列表
     * @return \think\Response
     */
    public function delete(string $ids): \think\Response
    {
        (new UserService())->deleteUsers($ids);
        return $this->ok();
    }

    /**
     * 重置指定用户密码
     *
     * @OA\Put(
     *     path="/api/v1/users/{id}/password/reset",
     *     summary="重置指定用户密码",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="id", in="path", description="用户ID", required=true),
     *     @OA\Parameter(name="password", in="query", description="新密码", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $id 用户ID
     * @return \think\Response
     * @throws BusinessException 参数缺失时抛出
     */
    public function resetPassword(int $id): \think\Response
    {
        $password = (string) $this->request->param('password', '');
        if ($password === '') {
            $json = $this->getJsonBody();
            $password = (string) ($json['password'] ?? '');
        }

        // 兼容参数传递
        (new UserService())->resetUserPassword($id, $password);
        return $this->ok();
    }

    /**
     * 修改用户状态
     *
     * @OA\Patch(
     *     path="/api/v1/users/{userId}/status",
     *     summary="修改用户状态",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="userId", in="path", description="用户ID", required=true),
     *     @OA\Parameter(name="status", in="query", description="用户状态(1:启用;0:禁用)", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @param int $userId 用户ID
     * @return \think\Response
     * @throws BusinessException 参数缺失时抛出
     */
    public function updateStatus(int $userId): \think\Response
    {
        $status = $this->request->param('status');
        if ($status === null || $status === '') {
            $json = $this->getJsonBody();
            $status = $json['status'] ?? null;
        }

        if ($status === null || $status === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        // 状态转为整型
        (new UserService())->updateUserStatus($userId, (int) $status);
        return $this->ok();
    }

    /**
     * 用户下拉选项
     *
     * @OA\Get(
     *     path="/api/v1/users/options",
     *     summary="获取用户下拉选项",
     *     tags={"02.用户接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function options(): \think\Response
    {
        $list = (new UserService())->listUserOptions();
        return $this->ok($list);
    }

    /**
     * 个人中心用户信息
     *
     * @OA\Get(
     *     path="/api/v1/users/profile",
     *     summary="获取个人中心用户信息",
     *     tags={"02.用户接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function profile(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = (new UserService())->getUserProfile($userId);
        return $this->ok($data);
    }

    /**
     * 个人中心修改用户信息
     *
     * @OA\Put(
     *     path="/api/v1/users/profile",
     *     summary="个人中心修改用户信息",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function updateProfile(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->updateUserProfile($userId, $data);
        return $this->ok(true);
    }

    /**
     * 当前用户修改密码
     *
     * @OA\Put(
     *     path="/api/v1/users/password",
     *     summary="当前用户修改密码",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function changePassword(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->changeCurrentUserPassword($userId, $data);
        return $this->ok(true);
    }

    /**
     * 发送短信验证码（绑定或更换手机号）
     *
     * @OA\Post(
     *     path="/api/v1/users/mobile/code",
     *     summary="发送短信验证码（绑定或更换手机号）",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="mobile", in="query", description="手机号码", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 参数缺失时抛出
     */
    public function sendMobileCode(): \think\Response
    {
        $mobile = (string) $this->request->param('mobile', '');
        if ($mobile === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }
        (new UserService())->sendMobileCode($mobile);
        return $this->ok(true);
    }

    /**
     * 绑定或更换手机号
     *
     * @OA\Put(
     *     path="/api/v1/users/mobile",
     *     summary="绑定或更换手机号",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function bindOrChangeMobile(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->bindOrChangeMobile($userId, $data);
        return $this->ok(true);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/mobile",
     *     summary="解绑手机号",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function unbindMobile(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->unbindMobile($userId, $data);
        return $this->ok(true);
    }

    /**
     * 发送邮箱验证码（绑定或更换邮箱）
     *
     * @OA\Post(
     *     path="/api/v1/users/email/code",
     *     summary="发送邮箱验证码（绑定或更换邮箱）",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="email", in="query", description="邮箱地址", required=true),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 参数缺失时抛出
     */
    public function sendEmailCode(): \think\Response
    {
        $email = (string) $this->request->param('email', '');
        if ($email === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }
        (new UserService())->sendEmailCode($email);
        return $this->ok(true);
    }

    /**
     * 绑定或更换邮箱
     *
     * @OA\Put(
     *     path="/api/v1/users/email",
     *     summary="绑定或更换邮箱",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function bindOrChangeEmail(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->bindOrChangeEmail($userId, $data);
        return $this->ok(true);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/email",
     *     summary="解绑邮箱",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function unbindEmail(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->unbindEmail($userId, $data);
        return $this->ok(true);
    }

    /**
     * 下载用户导入模板
     *
     * @OA\Get(
     *     path="/api/v1/users/template",
     *     summary="用户导入模板下载",
     *     tags={"02.用户接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function downloadTemplate(): \think\Response
    {
        $bin = (new UserService())->buildUserImportTemplateXlsx();
        $fileName = '用户导入模板.xlsx';

        return response($bin, 200)
            ->header([
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($fileName),
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
    }

    /**
     * 导出用户
     *
     * @OA\Get(
     *     path="/api/v1/users/export",
     *     summary="导出用户",
     *     tags={"02.用户接口"},
     *     @OA\Response(response=200, description="OK")
     * )
     *
     * @return \think\Response
     */
    public function export(): \think\Response
    {
        $authUser = $this->getAuthUser();
        $bin = (new UserService())->exportUsersXlsx($this->request->param(), $authUser);
        $fileName = '用户列表.xlsx';

        return response($bin, 200)
            ->header([
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($fileName),
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
    }

    /**
     * 导入用户
     *
     * @OA\Post(
     *     path="/api/v1/users/import",
     *     summary="导入用户",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="deptId", in="query", description="部门ID", required=false),
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
     *
     * @return \think\Response
     * @throws BusinessException 未上传文件时抛出
     */
    public function import(): \think\Response
    {
        $deptId = $this->request->param('deptId');
        $file = $this->request->file('file');
        if ($file === null) {
            throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION);
        }

        $data = (new UserService())->importUsersFromXlsx($file, $deptId);
        return $this->ok($data);
    }

}
