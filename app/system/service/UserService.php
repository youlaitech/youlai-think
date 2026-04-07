<?php
declare(strict_types=1);

namespace app\system\service;

use app\common\exception\BusinessException;
use app\common\util\PageUtil;
use app\common\util\VerifyCodeHelper;
use app\system\model\Dept;
use app\system\model\Role;
use app\system\model\User;
use app\system\model\UserRole;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\facade\Db;

final class UserService
{
    /**
     * 用户字段列表（用于列表查询）
     */
    private const LIST_FIELDS = [
        'id', 'username', 'nickname', 'mobile', 'email',
        'avatar', 'gender', 'status', 'dept_id', 'create_time',
    ];

    /**
     * 默认密码
     */
    private const DEFAULT_PASSWORD = '123456';

    /**
     * 根据ID获取用户详情
     */
    public function getById(int $id): ?array
    {
        $user = User::with(['dept', 'roles'])->find($id);

        if (!$user) {
            return null;
        }

        $data = $user->toArray();
        $data['roleIds'] = array_column($data['roles'] ?? [], 'id');
        unset($data['roles']);

        return $data;
    }

    /**
     * 根据用户名获取用户
     */
    public function getByUsername(string $username): ?array
    {
        return User::where('username', $username)->find()?->toArray();
    }

    /**
     * 分页查询用户列表
     */
    public function paginate(array $params, array $authUser): array
    {
        [$page, $pageSize] = PageUtil::resolve($params);

        $query = User::with(['dept'])
            ->field(array_merge(self::LIST_FIELDS, ['dept_id']))
            ->order('id', 'desc');

        // 条件筛选
        $this->applyFilters($query, $params);

        // 数据权限过滤
        $this->applyDataScope($query, $authUser);

        $total = $query->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        $userIds = array_values(array_filter(array_map(static fn ($item) => $item['id'] ?? null, $list)));
        $roleNameMap = [];
        if (!empty($userIds)) {
            $rows = Db::name('sys_user_role')
                ->alias('sur')
                ->leftJoin('sys_role r', 'sur.role_id = r.id AND r.is_deleted = 0')
                ->whereIn('sur.user_id', $userIds)
                ->group('sur.user_id')
                ->field('sur.user_id, GROUP_CONCAT(r.name) AS roleNames')
                ->select()
                ->toArray();

            foreach ($rows as $row) {
                $roleNameMap[(string) ($row['user_id'] ?? '')] = (string) ($row['roleNames'] ?? '');
            }
        }

        // 格式化输出
        foreach ($list as &$item) {
            $item['deptName'] = $item['dept']['name'] ?? '';
            $item['roleNames'] = $roleNameMap[(string) ($item['id'] ?? '')] ?? '';
            $item['genderText'] = $item['gender'] == 1 ? '男' : ($item['gender'] == 2 ? '女' : '未知');
            $item['statusText'] = $item['status'] == 1 ? '启用' : '禁用';
            unset($item['dept']);
        }

        return [$list, $total];
    }

    /**
     * 创建用户
     */
    public function create(array $data): int
    {
        // 检查用户名是否重复
        if (User::where('username', $data['username'])->find()) {
            throw new BusinessException('用户名已存在');
        }

        return Db::transaction(function () use ($data) {
            $now = date('Y-m-d H:i:s');

            $password = $data['password'] ?? '';
            if ($password === '' || $password === null) {
                $password = self::DEFAULT_PASSWORD;
            }

            // 创建用户
            $userId = User::insertGetId([
                'username' => $data['username'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'nickname' => $data['nickname'],
                'mobile' => $data['mobile'] ?? '',
                'email' => $data['email'] ?? '',
                'avatar' => $data['avatar'] ?? '',
                'gender' => $data['gender'] ?? 0,
                'status' => $data['status'] ?? 1,
                'dept_id' => $data['dept_id'] ?? 0,
                'create_time' => $now,
                'update_time' => $now,
            ]);

            // 分配角色
            if (!empty($data['role_ids'])) {
                $this->assignRoles($userId, $data['role_ids']);
            }

            return (int) $userId;
        });
    }

    /**
     * 更新用户
     */
    public function update(int $id, array $data): bool
    {
        $user = User::find($id);
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        return Db::transaction(function () use ($user, $data) {
            // 更新基本信息
            $user->nickname = $data['nickname'] ?? $user->nickname;
            $user->mobile = $data['mobile'] ?? $user->mobile;
            $user->email = $data['email'] ?? $user->email;
            $user->avatar = $data['avatar'] ?? $user->avatar;
            $user->gender = $data['gender'] ?? $user->gender;
            $user->status = $data['status'] ?? $user->status;
            $user->dept_id = $data['dept_id'] ?? $user->dept_id;

            // 更新密码（如果提供了）
            if (!empty($data['password'])) {
                $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $user->save();

            // 更新角色
            if (isset($data['role_ids'])) {
                $this->syncRoles($id, $data['role_ids']);
            }

            return true;
        });
    }

    /**
     * 批量删除用户
     */
    public function deleteByIds(array $ids): int
    {
        // 不允许删除超级管理员
        $adminIds = User::where('username', 'admin')->column('id');
        $ids = array_diff($ids, $adminIds);

        if (empty($ids)) {
            return 0;
        }

        return Db::transaction(function () use ($ids) {
            // 删除用户角色关联
            UserRole::whereIn('user_id', $ids)->delete();

            // 软删除用户
            Db::name('sys_user')->whereIn('id', $ids)->update([
                'is_deleted' => 1,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            return count($ids);
        });
    }

    /**
     * 获取当前用户信息
     */
    public function getCurrentUser(int $userId): array
    {
        $user = User::with(['dept', 'roles'])->find($userId);

        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        // 获取角色编码
        $roleCodes = array_column($user->roles->toArray() ?? [], 'code');

        // 获取权限标识
        $perms = [];
        if (!empty($roleCodes)) {
            $perms = app()->make(RoleService::class)->getPermissionsByUserId($userId);
        }

        $roleNames = [];
        foreach ($user->roles ?? [] as $role) {
            $roleNames[] = $role->name ?? '';
        }

        return [
            'userId' => (string) $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'gender' => (int) ($user->gender ?? 0),
            'deptName' => $user->dept->name ?? '',
            'roles' => $roleCodes,
            'roleNames' => $roleNames,
            'perms' => $perms,
        ];
    }

    /**
     * 获取用户下拉选项
     */
    public function getOptions(): array
    {
        $list = User::where('status', 1)
            ->field(['id', 'username', 'nickname'])
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return array_map(fn($item) => [
            'value' => (string) $item['id'],
            'label' => $item['nickname'] ?: $item['username'],
        ], $list);
    }

    /**
     * 获取个人中心用户信息
     */
    public function getProfile(int $userId): array
    {
        $user = User::with(['dept', 'roles'])->find($userId);

        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        $data = $user->toArray();
        $data['roleNames'] = implode(', ', array_column($data['roles'] ?? [], 'name'));
        $data['deptName'] = $data['dept']['name'] ?? '';
        $data['genderLabel'] = $data['gender'] == 1 ? '男' : ($data['gender'] == 2 ? '女' : '未知');

        return $data;
    }

    /**
     * 更新个人中心用户信息
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        $user->nickname = $data['nickname'] ?? $user->nickname;
        $user->avatar = $data['avatar'] ?? $user->avatar;
        $user->gender = $data['gender'] ?? $user->gender;
        $user->email = $data['email'] ?? $user->email;
        $user->mobile = $data['mobile'] ?? $user->mobile;

        return $user->save();
    }

    /**
     * 修改用户密码
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        // 验证原密码
        if (!password_verify($oldPassword, $user->password)) {
            throw new BusinessException('原密码错误');
        }

        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        return $user->save();
    }

    /**
     * 获取用户表单数据
     */
    public function getFormById(int $id): ?array
    {
        $user = User::with(['roles'])->find($id);

        if (!$user) {
            return null;
        }

        $data = $user->toArray();
        $data['roleIds'] = array_column($data['roles'] ?? [], 'id');
        unset($data['roles'], $data['password']);

        return $data;
    }

    /**
     * 发送短信验证码（绑定或更换手机号）
     */
    public function sendMobileCode(int $userId, string $mobile): void
    {
        // 检查手机号是否已被其他用户绑定
        $exists = User::where('mobile', $mobile)
            ->where('id', '<>', $userId)
            ->find();

        if ($exists) {
            throw new BusinessException('手机号已被其他账号绑定');
        }

        // 生成验证码并缓存
        VerifyCodeHelper::generateAndCache('sms:mobile', $mobile);
    }

    /**
     * 绑定或更换手机号
     */
    public function bindOrChangeMobile(int $userId, string $mobile, string $code, string $password): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        // 验证密码
        if (!password_verify($password, $user->password)) {
            throw new BusinessException('密码错误');
        }

        // 验证验证码
        if (!VerifyCodeHelper::verify('sms:mobile', $mobile, $code)) {
            throw new BusinessException('验证码错误或已过期');
        }

        // 检查手机号是否已被其他用户绑定
        $exists = User::where('mobile', $mobile)
            ->where('id', '<>', $userId)
            ->find();

        if ($exists) {
            throw new BusinessException('手机号已被其他账号绑定');
        }

        // 更新手机号
        $user->mobile = $mobile;
        return $user->save();
    }

    /**
     * 解绑手机号
     */
    public function unbindMobile(int $userId, string $password): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        if (empty($user->mobile)) {
            throw new BusinessException('当前账号未绑定手机号');
        }

        // 验证密码
        if (!password_verify($password, $user->password)) {
            throw new BusinessException('密码错误');
        }

        $user->mobile = null;
        return $user->save();
    }

    /**
     * 发送邮箱验证码（绑定或更换邮箱）
     */
    public function sendEmailCode(int $userId, string $email): void
    {
        // 检查邮箱是否已被其他用户绑定
        $exists = User::where('email', $email)
            ->where('id', '<>', $userId)
            ->find();

        if ($exists) {
            throw new BusinessException('邮箱已被其他账号绑定');
        }

        // 生成验证码并缓存
        VerifyCodeHelper::generateAndCache('sms:email', $email);
    }

    /**
     * 绑定或更换邮箱
     */
    public function bindOrChangeEmail(int $userId, string $email, string $code, string $password): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        // 验证密码
        if (!password_verify($password, $user->password)) {
            throw new BusinessException('密码错误');
        }

        // 验证验证码
        if (!VerifyCodeHelper::verify('sms:email', $email, $code)) {
            throw new BusinessException('验证码错误或已过期');
        }

        // 检查邮箱是否已被其他用户绑定
        $exists = User::where('email', $email)
            ->where('id', '<>', $userId)
            ->find();

        if ($exists) {
            throw new BusinessException('邮箱已被其他账号绑定');
        }

        // 更新邮箱
        $user->email = $email;
        return $user->save();
    }

    /**
     * 解绑邮箱
     */
    public function unbindEmail(int $userId, string $password): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new BusinessException('用户不存在');
        }

        if (empty($user->email)) {
            throw new BusinessException('当前账号未绑定邮箱');
        }

        // 验证密码
        if (!password_verify($password, $user->password)) {
            throw new BusinessException('密码错误');
        }

        $user->email = null;
        return $user->save();
    }

    // ==================== 私有方法 ====================

    /**
     * 应用查询条件
     */
    private function applyFilters($query, array $params): void
    {
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereLike('username', "%{$keyword}%")
                    ->whereOr('nickname', 'like', "%{$keyword}%")
                    ->whereOr('mobile', 'like', "%{$keyword}%");
            });
        }

        if (isset($params['createTime']) && $params['createTime'] !== '') {
            $range = $params['createTime'];

            if (is_string($range)) {
                $range = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $range) ?: [])));
            }

            if (is_array($range) && count($range) >= 2) {
                $start = (string) ($range[0] ?? '');
                $end = (string) ($range[1] ?? '');

                if ($start !== '' && $end !== '') {
                    $startTime = date('Y-m-d 00:00:00', strtotime($start));
                    $endTime = date('Y-m-d 23:59:59', strtotime($end));
                    $query->whereBetweenTime('create_time', [$startTime, $endTime]);
                }
            }
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        if (!empty($params['dept_id'])) {
            $query->where('dept_id', (int) $params['dept_id']);
        }

        if (!empty($params['gender'])) {
            $query->where('gender', (int) $params['gender']);
        }
    }

    /**
     * 应用数据权限
     */
    private function applyDataScope($query, array $authUser): void
    {
        // 从 authorities 提取角色编码
        $authorities = (array) ($authUser['authorities'] ?? []);
        $roleCodes = array_map(fn($a) => str_starts_with($a, 'ROLE_') ? substr($a, 5) : $a, $authorities);

        // 超级管理员不过滤
        if (in_array('ROOT', $roleCodes, true)) {
            return;
        }

        // 管理员也跳过数据权限过滤
        if (in_array('ADMIN', $roleCodes, true)) {
            return;
        }

        // 根据数据权限过滤
        $dataScopes = $authUser['dataScopes'] ?? [];
        if (empty($dataScopes)) {
            // 无数据权限配置时，仅显示本人数据
            $userId = (int) ($authUser['userId'] ?? 0);
            if ($userId > 0) {
                $query->where('id', $userId);
            }
            return;
        }

        // 合并部门权限
        $deptIds = [];
        foreach ($dataScopes as $scope) {
            if (!empty($scope['customDeptIds'])) {
                $deptIds = array_merge($deptIds, $scope['customDeptIds']);
            }
        }

        if (!empty($deptIds)) {
            $query->whereIn('dept_id', array_unique($deptIds));
        }
    }

    /**
     * 分配角色
     */
    private function assignRoles(int $userId, array $roleIds): void
    {
        $now = date('Y-m-d H:i:s');
        $data = array_map(fn ($roleId) => [
            'user_id' => $userId,
            'role_id' => $roleId,
            'create_time' => $now,
        ], $roleIds);

        UserRole::insertAll($data);
    }

    /**
     * 同步角色
     */
    private function syncRoles(int $userId, array $roleIds): void
    {
        UserRole::where('user_id', $userId)->delete();

        if (!empty($roleIds)) {
            $this->assignRoles($userId, $roleIds);
        }
    }

    // ==================== 导入导出 ====================

    /**
     * 生成用户导入模板
     */
    public function generateImportTemplate(): string
    {
        $templatePath = public_path() . 'static/templates/用户导入模板.xlsx';

        if (!file_exists($templatePath)) {
            throw new BusinessException('模板文件不存在');
        }

        return $templatePath;
    }

    public function exportToExcel(array $params, array $authUser): string
    {
        $query = User::with(['dept'])
            ->field(array_merge(self::LIST_FIELDS, ['dept_id']))
            ->order('id', 'desc');

        $this->applyFilters($query, $params);
        $this->applyDataScope($query, $authUser);

        $list = $query->select()->toArray();

        $userIds = array_values(array_filter(array_map(static fn ($item) => $item['id'] ?? null, $list)));
        $roleNameMap = [];
        if (!empty($userIds)) {
            $rows = Db::name('sys_user_role')
                ->alias('sur')
                ->leftJoin('sys_role r', 'sur.role_id = r.id AND r.is_deleted = 0')
                ->whereIn('sur.user_id', $userIds)
                ->group('sur.user_id')
                ->field('sur.user_id, GROUP_CONCAT(r.name) AS roleNames')
                ->select()
                ->toArray();

            foreach ($rows as $row) {
                $roleNameMap[(string) ($row['user_id'] ?? '')] = (string) ($row['roleNames'] ?? '');
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('用户列表');

        $headers = ['用户名', '昵称', '手机号', '邮箱', '性别', '部门', '角色', '创建时间'];
        foreach ($headers as $i => $header) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . '1';
            $sheet->setCellValue($cell, $header);
        }

        $rowNum = 2;
        foreach ($list as $item) {
            $deptName = $item['dept']['name'] ?? '';
            $roleNames = $roleNameMap[(string) ($item['id'] ?? '')] ?? '';
            $genderText = ($item['gender'] ?? 0) == 1 ? '男' : (($item['gender'] ?? 0) == 2 ? '女' : '未知');

            $sheet->setCellValue('A' . $rowNum, (string) ($item['username'] ?? ''));
            $sheet->setCellValue('B' . $rowNum, (string) ($item['nickname'] ?? ''));
            $sheet->setCellValue('C' . $rowNum, (string) ($item['mobile'] ?? ''));
            $sheet->setCellValue('D' . $rowNum, (string) ($item['email'] ?? ''));
            $sheet->setCellValue('E' . $rowNum, $genderText);
            $sheet->setCellValue('F' . $rowNum, $deptName);
            $sheet->setCellValue('G' . $rowNum, $roleNames);
            $sheet->setCellValue('H' . $rowNum, (string) ($item['create_time'] ?? ''));
            $rowNum++;
        }

        $tempDir = runtime_path() . 'temp' . DIRECTORY_SEPARATOR;
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $filePath = $tempDir . 'users_export_' . date('YmdHis') . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * 从Excel导入用户
     */
    public function importFromExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            return ['validCount' => 0, 'invalidCount' => 0, 'messageList' => ['Excel文件没有数据']];
        }

        // 预加载角色和部门数据（支持编码或名称匹配）
        $roleMap = [];
        $roles = Role::where('status', 1)->select();
        foreach ($roles as $role) {
            if ($role->code) {
                $roleMap[$role->code] = $role->id;
            }
            if ($role->name) {
                $roleMap[$role->name] = $role->id;
            }
        }

        $deptMap = [];
        $depts = Dept::select();
        foreach ($depts as $dept) {
            if ($dept->code) {
                $deptMap[$dept->code] = $dept->id;
            }
            if ($dept->name) {
                $deptMap[$dept->name] = $dept->id;
            }
        }

        // 获取已存在的用户名
        $existingUsernames = User::column('username');

        $validCount = 0;
        $invalidCount = 0;
        $messageList = [];

        // 跳过表头
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;

            $username = trim((string)($row[0] ?? ''));
            $nickname = trim((string)($row[1] ?? ''));
            $genderStr = trim((string)($row[2] ?? ''));
            $mobile = trim((string)($row[3] ?? ''));
            $email = trim((string)($row[4] ?? ''));
            $roleStr = trim((string)($row[5] ?? ''));
            $deptStr = trim((string)($row[6] ?? ''));

            // 校验必填字段
            if ($username === '' || $nickname === '') {
                $messageList[] = "第{$rowNum}行：用户名和昵称不能为空";
                $invalidCount++;
                continue;
            }

            // 检查用户名是否已存在
            if (in_array($username, $existingUsernames, true)) {
                $messageList[] = "第{$rowNum}行：用户名\"{$username}\"已存在";
                $invalidCount++;
                continue;
            }

            // 解析性别
            $gender = 0;
            if ($genderStr !== '') {
                if (in_array($genderStr, ['1', '男'], true)) {
                    $gender = 1;
                } elseif (in_array($genderStr, ['2', '女'], true)) {
                    $gender = 2;
                }
            }

            // 解析角色
            $roleIds = [];
            if ($roleStr !== '') {
                $roleParts = array_map('trim', explode(',', $roleStr));
                foreach ($roleParts as $part) {
                    if ($part === '') {
                        continue;
                    }
                    if (isset($roleMap[$part])) {
                        $roleIds[] = $roleMap[$part];
                    }
                }
            }

            if (empty($roleIds)) {
                $messageList[] = "第{$rowNum}行：角色不存在或为空";
                $invalidCount++;
                continue;
            }

            // 解析部门
            $deptId = 0;
            if ($deptStr !== '') {
                if (isset($deptMap[$deptStr])) {
                    $deptId = $deptMap[$deptStr];
                } else {
                    $messageList[] = "第{$rowNum}行：部门\"{$deptStr}\"不存在";
                    $invalidCount++;
                    continue;
                }
            }

            // 创建用户
            $now = date('Y-m-d H:i:s');
            $userId = User::insertGetId([
                'username' => $username,
                'password' => password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT),
                'nickname' => $nickname,
                'mobile' => $mobile,
                'email' => $email,
                'gender' => $gender,
                'status' => 1,
                'dept_id' => $deptId,
                'create_time' => $now,
                'update_time' => $now,
            ]);

            // 分配角色
            $this->assignRoles($userId, array_unique($roleIds));

            $existingUsernames[] = $username;
            $validCount++;
        }

        return [
            'validCount' => $validCount,
            'invalidCount' => $invalidCount,
            'messageList' => $messageList,
        ];
    }
}
