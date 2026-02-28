<?php
declare(strict_types=1);

namespace app\service;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\Dept;
use app\model\Role;
use app\model\User;
use app\model\UserRole;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\facade\Db;

/**
 * 用户服务。
 * 负责用户相关的业务逻辑处理。
 */
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
        $page = (int) ($params['pageNum'] ?? 1);
        $pageSize = min((int) ($params['pageSize'] ?? 10), 100);

        $query = User::with(['dept'])
            ->field(array_merge(self::LIST_FIELDS, ['dept_id']))
            ->order('id', 'desc');

        // 条件筛选
        $this->applyFilters($query, $params);

        // 数据权限过滤
        $this->applyDataScope($query, $authUser);

        $total = $query->count();
        $list = $query->page($page, $pageSize)->select()->toArray();

        // 格式化输出
        foreach ($list as &$item) {
            $item['deptName'] = $item['dept']['name'] ?? '';
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
        if (User::where('username', $data['username'])->where('is_deleted', 0)->find()) {
            throw new BusinessException(ResultCode::USER_ERROR, '用户名已存在');
        }

        return Db::transaction(function () use ($data) {
            $now = date('Y-m-d H:i:s');

            $password = $data['password'] ?? '';
            if ($password === '' || $password === null) {
                $password = '123456';
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
            throw new BusinessException(ResultCode::USER_ERROR, '用户不存在');
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
            return User::destroy($ids);
        });
    }

    /**
     * 获取当前用户信息
     */
    public function getCurrentUser(int $userId): array
    {
        $user = User::with(['dept', 'roles'])->find($userId);

        if (!$user) {
            throw new BusinessException(ResultCode::USER_ERROR, '用户不存在');
        }

        return [
            'id' => (string) $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'deptId' => (string) $user->dept_id,
            'deptName' => $user->dept?->name ?? '',
            'roleCodes' => array_column($user->roles->toArray() ?? [], 'code'),
        ];
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
        // 超级管理员不过滤
        if (in_array('ROOT', $authUser['roleCodes'] ?? [], true)) {
            return;
        }

        // 根据数据权限过滤
        $dataScopes = $authUser['dataScopes'] ?? [];
        if (empty($dataScopes)) {
            $query->whereRaw('1 = 0'); // 无权限
            return;
        }

        // 合并部门权限
        $deptIds = [];
        foreach ($dataScopes as $scope) {
            if (!empty($scope['deptIds'])) {
                $deptIds = array_merge($deptIds, $scope['deptIds']);
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
        // 使用静态模板文件
        $templatePath = public_path() . 'static/templates/excel/用户导入模板.xlsx';
        
        if (!file_exists($templatePath)) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, '模板文件不存在');
        }
        
        return $templatePath;
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
        $existingUsernames = User::where('is_deleted', 0)->column('username');

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
                'password' => password_hash('123456', PASSWORD_DEFAULT),
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
