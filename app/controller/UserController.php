<?php declare(strict_types=1);

namespace app\controller;

use app\controller\ApiController;
use app\service\UserService;
use app\validate\UserValidate;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="02.用户管理")
 */
final class UserController extends ApiController
{
    /**
     * 获取当前用户信息
     *
     * @OA\Get(
     *     path="/api/v1/users/me",
     *     summary="获取当前用户信息",
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
     */
    public function page(): \think\response\Json
    {
        [$list, $total] = $this->service(UserService::class)->paginate(
            $this->getAllParams(),
            $this->getAuthUser()
        );

        $pagination = $this->getPaginationParams();

        return $this->successPaginate($list, $total, $pagination['page'], $pagination['pageSize']);
    }

    /**
     * 获取用户详情
     */
    public function detail(): \think\response\Json
    {
        $id = $this->getIdParam();
        $data = $this->service(UserService::class)->getById($id);

        if (!$data) {
            return $this->fail('A0400', '用户不存�?);
        }

        return $this->success($data);
    }

    /**
     * 创建用户
     */
    public function create(): \think\response\Json
    {
        $this->checkDemo();

        $data = $this->validate($this->getAllParams(), UserValidate::class, 'create');

        $id = $this->service(UserService::class)->create($data);

        return $this->success(['id' => (string) $id], '创建成功');
    }

    /**
     * 更新用户
     */
    public function update(): \think\response\Json
    {
        $this->checkDemo();

        $id = $this->getIdParam();
        $data = $this->validate($this->getAllParams(), UserValidate::class, 'update');

        $this->service(UserService::class)->update($id, $data);

        return $this->success(null, '更新成功');
    }

    /**
     * 批量删除用户
     */
    public function delete(): \think\response\Json
    {
        $this->checkDemo();

        $ids = $this->getIdsParam();

        if (empty($ids)) {
            return $this->fail('A0410', '请选择要删除的用户');
        }

        $count = $this->service(UserService::class)->deleteByIds($ids);

        return $this->success(['count' => $count], "成功删除 {$count} 个用�?);
    }

    /**
     * 重置密码
     */
    public function resetPassword(): \think\response\Json
    {
        $this->checkDemo();

        $id = $this->getIdParam();
        $password = $this->getParam('password', '123456');

        $this->service(UserService::class)->update($id, [
            'password' => $password,
        ]);

        return $this->success(null, '密码重置成功');
    }

    /**
     * 修改状�?
     */
    public function changeStatus(): \think\response\Json
    {
        $this->checkDemo();

        $id = $this->getIdParam();
        $status = (int) $this->getParam('status', 1);

        $this->service(UserService::class)->update($id, [
            'status' => $status,
        ]);

        return $this->success(null, '状态修改成�?);
    }

    /**
     * 下载导入模板
     */
    public function template(): \think\response\File
    {
        $tempPath = $this->service(UserService::class)->generateImportTemplate();

        return download($tempPath, 'user_import_template.xlsx');
    }

    /**
     * 导入用户
     */
    public function import(): \think\response\Json
    {
        $this->checkDemo();

        $file = $this->request->file('file');
        if (!$file) {
            return $this->fail('A0400', '请上传文�?);
        }

        // 保存上传文件
        $saveName = $file->move(runtime_path() . 'temp')->getPathname();

        $result = $this->service(UserService::class)->importFromExcel($saveName);

        // 删除临时文件
        @unlink($saveName);

        return $this->success($result, "导入完成，成功{$result['validCount']}条，失败{$result['invalidCount']}�?);
    }
}
