<?php declare(strict_types=1);

namespace app\system\controller;

use app\controller\BaseController;
use app\system\annotation\Log;
use app\system\enums\ActionType;
use app\system\service\UserService;
use app\system\validate\UserValidate;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="02.用户接口")
 */
final class UserController extends BaseController
{
    /**
     * 获取当前用户信息
     *
     * @OA\Get(
     *     path="/api/v1/users/me",
     *     summary="获取当前用户信息",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function me(): \think\response\Json
    {
        $data = $this->service(UserService::class)->getCurrentUser(
            $this->getAuthUserId()
        );

        return $this->success($data);
    }

    /**
     * 分页查询用户列表
     *
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="用户列表",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(ActionType::USER_LIST)]
    public function page(): \think\response\Json
    {
        [$list, $total] = $this->service(UserService::class)->paginate(
            $this->getAllParams(),
            $this->getAuthUser()
        );

        return $this->success($list, $total);
    }

    /**
     * 获取用户详情
     *
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     summary="获取用户详情",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="id", in="path", description="用户ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function detail(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(UserService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '用户不存在');
        }

        return $this->success($data);
    }

    /**
     * 创建用户
     *
     * @OA\Post(
     *     path="/api/v1/users",
     *     summary="新增用户",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::USER_CREATE)]
    public function create(): \think\response\Json
    {
        $data = $this->validate($this->getAllParams(), UserValidate::class, 'create');

        $id = $this->service(UserService::class)->create($data);

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新用户
     *
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     summary="修改用户",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="id", in="path", description="用户ID", required=true),
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::USER_UPDATE)]
    public function update(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->validate($this->getAllParams(), UserValidate::class, 'update');

        $this->service(UserService::class)->update($id, $data);

        return $this->success(null, '更新成功');
    }

    /**
     * 批量删除用户
     *
     * @OA\Delete(
     *     path="/api/v1/users/{ids}",
     *     summary="删除用户",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="ids", in="path", description="用户ID，多个以英文逗号分割", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::USER_DELETE)]
    public function delete(): \think\response\Json
    {
        $ids = $this->getIdsParam();

        if (empty($ids)) {
            return $this->fail('A0410', '请选择要删除的用户');
        }

        $count = $this->service(UserService::class)->deleteByIds($ids);

        return $this->success(['count' => $count], "成功删除 {$count} 个用户");
    }

    /**
     * 重置密码
     *
     * @OA\Patch(
     *     path="/api/v1/users/{id}/password/reset",
     *     summary="重置密码",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="id", in="path", description="用户ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::CHANGE_PWD)]
    public function resetPassword(): \think\response\Json
    {
        $id = $this->getIdParam();
        $password = $this->getParam('password', '123456');

        $this->service(UserService::class)->update($id, [
            'password' => $password,
        ]);

        return $this->success(null, '密码重置成功');
    }

    /**
     * 修改状态
     *
     * @OA\Patch(
     *     path="/api/v1/users/{id}/status",
     *     summary="修改用户状态",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="id", in="path", description="用户ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::USER_UPDATE)]
    public function changeStatus(): \think\response\Json
    {
        $id = $this->getIdParam();
        $status = (int) $this->getParam('status', 1);

        $this->service(UserService::class)->update($id, [
            'status' => $status,
        ]);

        return $this->success(null, '状态修改成功');
    }

    /**
     * 下载导入模板
     *
     * @OA\Get(
     *     path="/api/v1/users/template",
     *     summary="下载用户导入模板",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function template(): \think\response\File
    {
        $tempPath = $this->service(UserService::class)->generateImportTemplate();

        $fileName = '用户导入模板.xlsx';
        $encodedFileName = rawurlencode($fileName);
        return download($tempPath, $fileName)->header([
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $encodedFileName . '"; filename*=UTF-8\'\'' . $encodedFileName,
        ]);
    }

    /**
     * 导出用户
     *
     * @OA\Get(
     *     path="/api/v1/users/export",
     *     summary="导出用户",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function export(): \think\response\File
    {
        $filePath = $this->service(UserService::class)->exportToExcel(
            $this->getAllParams(),
            $this->getAuthUser()
        );

        $fileName = '用户列表.xlsx';
        $encodedFileName = rawurlencode($fileName);
        return download($filePath, $fileName)->header([
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $encodedFileName . '"; filename*=UTF-8\'\'' . $encodedFileName,
        ]);
    }

    /**
     * 导入用户
     *
     * @OA\Post(
     *     path="/api/v1/users/import",
     *     summary="导入用户",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function import(): \think\response\Json
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->fail('A0400', '请上传文件');
        }

        // 保存上传文件
        $saveName = $file->move(runtime_path() . 'temp')->getPathname();

        $result = $this->service(UserService::class)->importFromExcel($saveName);

        // 删除临时文件
        @unlink($saveName);

        return $this->success($result, "导入完成，成功{$result['validCount']}条，失败{$result['invalidCount']}条");
    }

    /**
     * 获取用户下拉选项
     *
     * @OA\Get(
     *     path="/api/v1/users/options",
     *     summary="用户下拉列表",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function options(): \think\response\Json
    {
        $list = $this->service(UserService::class)->getOptions();

        return $this->success($list);
    }

    /**
     * 获取个人中心用户信息
     *
     * @OA\Get(
     *     path="/api/v1/users/profile",
     *     summary="获取个人中心用户信息",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function profile(): \think\response\Json
    {
        $data = $this->service(UserService::class)->getProfile(
            $this->getAuthUserId()
        );

        return $this->success($data);
    }

    /**
     * 个人中心修改用户信息
     *
     * @OA\Put(
     *     path="/api/v1/users/profile",
     *     summary="修改个人中心用户信息",
     *     tags={"02.用户接口"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::UPDATE_PROFILE)]
    public function updateProfile(): \think\response\Json
    {
        $data = $this->getAllParams();

        $this->service(UserService::class)->updateProfile(
            $this->getAuthUserId(),
            $data
        );

        return $this->success(null, '修改成功');
    }

    /**
     * 当前用户修改密码
     *
     * @OA\Patch(
     *     path="/api/v1/users/password",
     *     summary="修改密码",
     *     tags={"02.用户接口"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::CHANGE_PWD)]
    public function changePassword(): \think\response\Json
    {
        $oldPassword = $this->getParam('oldPassword', '');
        $newPassword = $this->getParam('newPassword', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->fail('A0400', '原密码和新密码不能为空');
        }

        $this->service(UserService::class)->changePassword(
            $this->getAuthUserId(),
            $oldPassword,
            $newPassword
        );

        return $this->success(null, '密码修改成功');
    }

    /**
     * 获取用户表单数据
     *
     * @OA\Get(
     *     path="/api/v1/users/{id}/form",
     *     summary="获取用户表单数据",
     *     tags={"02.用户接口"},
     *     @OA\Parameter(name="id", in="path", description="用户ID", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function form(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(UserService::class)->getFormById($id);

        if (!$data) {
            return $this->fail('A0400', '用户不存在');
        }

        return $this->success($data);
    }

    /**
     * 发送短信验证码（绑定或更换手机号）
     */
    public function sendMobileCode(): \think\response\Json
    {
        $mobile = $this->getParam('mobile', '');

        if (empty($mobile)) {
            return $this->fail('A0400', '手机号不能为空');
        }

        $this->service(UserService::class)->sendMobileCode(
            $this->getAuthUserId(),
            $mobile
        );

        return $this->success(null, '验证码发送成功');
    }

    /**
     * 绑定或更换手机号
     */
    public function bindOrChangeMobile(): \think\response\Json
    {
        $mobile = $this->getParam('mobile', '');
        $code = $this->getParam('code', '');
        $password = $this->getParam('password', '');

        if (empty($mobile) || empty($code) || empty($password)) {
            return $this->fail('A0400', '参数不完整');
        }

        $this->service(UserService::class)->bindOrChangeMobile(
            $this->getAuthUserId(),
            $mobile,
            $code,
            $password
        );

        return $this->success(null, '手机号绑定成功');
    }

    /**
     * 解绑手机号
     */
    public function unbindMobile(): \think\response\Json
    {
        $password = $this->getParam('password', '');

        if (empty($password)) {
            return $this->fail('A0400', '密码不能为空');
        }

        $this->service(UserService::class)->unbindMobile(
            $this->getAuthUserId(),
            $password
        );

        return $this->success(null, '手机号解绑成功');
    }

    /**
     * 发送邮箱验证码（绑定或更换邮箱）
     */
    public function sendEmailCode(): \think\response\Json
    {
        $email = $this->getParam('email', '');

        if (empty($email)) {
            return $this->fail('A0400', '邮箱不能为空');
        }

        $this->service(UserService::class)->sendEmailCode(
            $this->getAuthUserId(),
            $email
        );

        return $this->success(null, '验证码发送成功');
    }

    /**
     * 绑定或更换邮箱
     */
    public function bindOrChangeEmail(): \think\response\Json
    {
        $email = $this->getParam('email', '');
        $code = $this->getParam('code', '');
        $password = $this->getParam('password', '');

        if (empty($email) || empty($code) || empty($password)) {
            return $this->fail('A0400', '参数不完整');
        }

        $this->service(UserService::class)->bindOrChangeEmail(
            $this->getAuthUserId(),
            $email,
            $code,
            $password
        );

        return $this->success(null, '邮箱绑定成功');
    }

    /**
     * 解绑邮箱
     */
    public function unbindEmail(): \think\response\Json
    {
        $password = $this->getParam('password', '');

        if (empty($password)) {
            return $this->fail('A0400', '密码不能为空');
        }

        $this->service(UserService::class)->unbindEmail(
            $this->getAuthUserId(),
            $password
        );

        return $this->success(null, '邮箱解绑成功');
    }

}
