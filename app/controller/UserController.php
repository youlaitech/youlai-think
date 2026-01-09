<?php

declare(strict_types=1);

namespace app\controller;

use app\common\controller\ApiController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\service\UserService;

/**
 * 用户接口 /api/v1/users
 *
 * 用户查询与管理
 */
final class UserController extends ApiController
{
    /**
     * 获取当前登录用户信息
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

        (new UserService())->resetUserPassword($id, $password);
        return $this->ok();
    }

    /**
     * 修改用户状态
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

        (new UserService())->updateUserStatus($userId, (int) $status);
        return $this->ok();
    }

    /**
     * 用户下拉选项
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
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function updateProfile(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->updateUserProfile($userId, $data);
        return $this->ok();
    }

    /**
     * 当前用户修改密码
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function changePassword(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->changeCurrentUserPassword($userId, $data);
        return $this->ok();
    }

    /**
     * 发送短信验证码（绑定或更换手机号）
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
        return $this->ok();
    }

    /**
     * 绑定或更换手机号
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function bindOrChangeMobile(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->bindOrChangeMobile($userId, $data);
        return $this->ok();
    }

    /**
     * 发送邮箱验证码（绑定或更换邮箱）
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
        return $this->ok();
    }

    /**
     * 绑定或更换邮箱
     *
     * @return \think\Response
     * @throws BusinessException 认证信息缺失或令牌无效时抛出
     */
    public function bindOrChangeEmail(): \think\Response
    {
        $userId = $this->getAuthUserId();
        $data = $this->mergeJsonParams();
        (new UserService())->bindOrChangeEmail($userId, $data);
        return $this->ok();
    }

    /**
     * 下载用户导入模板
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
