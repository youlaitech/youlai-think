<?php

declare(strict_types=1);

namespace app\service;

use app\common\redis\RedisClient;
use app\common\redis\RedisKey;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;

/**
 * 用户业务
 *
 * 用户管理 个人中心 导入导出
 */
final class UserService
{
    /**
     * 根据用户名查询用户。
     */
    public function getUserByUsername(string $username): ?array
    {
        $row = User::where('username', $username)
            ->where('is_deleted', 0)
            ->find();

        if ($row === null) {
            return null;
        }

        return $row->toArray();
    }

    public function getUserByEmail(string $email): ?array
    {
        $row = User::where('email', $email)
            ->where('is_deleted', 0)
            ->find();

        if ($row === null) {
            return null;
        }

        return $row->toArray();
    }

    public function getUserByMobile(string $mobile): ?array
    {
        $row = User::where('mobile', $mobile)
            ->where('is_deleted', 0)
            ->find();

        if ($row === null) {
            return null;
        }

        return $row->toArray();
    }

    public function getUserByOpenid(string $openid): ?array
    {
        $row = User::where('openid', $openid)
            ->where('is_deleted', 0)
            ->find();

        if ($row === null) {
            return null;
        }

        return $row->toArray();
    }

    /**
     * 获取用户下拉选项。
     */
    public function listUserOptions(): array
    {
        $rows = Db::name('sys_user')
            ->where('is_deleted', 0)
            ->where('status', 1)
            ->whereNotIn('id', function ($sub) {
                $sub->name('sys_user_role')
                    ->alias('ur')
                    ->join('sys_role r', 'ur.role_id = r.id')
                    ->where('r.code', 'ROOT')
                    ->field('ur.user_id');
            })
            ->order('id', 'desc')
            ->field('id,nickname')
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'label' => (string) ($r['nickname'] ?? ''),
                'value' => (string) ($r['id'] ?? ''),
            ];
        }

        return $list;
    }

    /**
     * 获取当前用户信息（含角色与权限）。
     */
    public function getCurrentUserDto(int $userId): array
    {
        $user = User::where('id', $userId)
            ->where('is_deleted', 0)
            ->field('id,username,nickname,avatar')
            ->find();

        $u = $user ? $user->toArray() : null;

        $roles = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->column('r.code');

        $roles = array_values(array_unique(array_filter($roles, fn($v) => $v !== null && $v !== '')));

        $perms = [];
        if (!empty($roles)) {
            $menuPerms = Db::name('sys_role_menu')
                ->alias('rm')
                ->join('sys_role r', 'rm.role_id = r.id')
                ->join('sys_menu m', 'rm.menu_id = m.id')
                ->whereIn('r.code', $roles)
                ->where('m.perm', '<>', '')
                ->where('m.perm', 'not null')
                ->column('m.perm');

            $perms = array_values(array_unique(array_filter($menuPerms, fn($v) => $v !== null && $v !== '')));
        }

        return [
            'userId' => (int) ($u['id'] ?? $userId),
            'username' => (string) ($u['username'] ?? ''),
            'nickname' => (string) ($u['nickname'] ?? ''),
            'avatar' => (string) ($u['avatar'] ?? ''),
            'roles' => $roles,
            'perms' => $perms,
        ];
    }

    /**
     * 用户分页列表。
     */
    public function getUserPage(array $queryParams, ?array $authUser = null): array
    {
        $pageNum = (int) ($queryParams['pageNum'] ?? 1);
        $pageSize = (int) ($queryParams['pageSize'] ?? 10);
        $pageNum = $pageNum > 0 ? $pageNum : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $keywords = trim((string) ($queryParams['keywords'] ?? ''));
        $status = $queryParams['status'] ?? null;
        $deptId = $queryParams['deptId'] ?? null;
        $createTime = $queryParams['createTime'] ?? null;

        $q = Db::name('sys_user')
            ->alias('u')
            ->leftJoin('sys_dept d', 'u.dept_id = d.id')
            ->where('u.is_deleted', 0);

        // root 用户（ROOT 角色）不在用户列表中展示
        $q = $q->whereNotIn('u.id', function ($sub) {
            $sub->name('sys_user_role')
                ->alias('ur')
                ->join('sys_role r', 'ur.role_id = r.id')
                ->where('r.code', 'ROOT')
                ->field('ur.user_id');
        });

        // 数据权限：1-所有数据 2-部门及子部门 3-本部门 4-本人
        if (is_array($authUser)) {
            $scope = (int) ($authUser['dataScope'] ?? 0);
            $authUserId = (int) ($authUser['userId'] ?? 0);
            $authDeptId = $authUser['deptId'] ?? null;
            $authDeptId = $authDeptId === null || $authDeptId === '' ? null : (int) $authDeptId;

            if ($scope === 4 && $authUserId > 0) {
                $q = $q->where('u.id', $authUserId);
            } elseif (($scope === 2 || $scope === 3) && $authDeptId !== null && $authDeptId > 0) {
                if ($scope === 3) {
                    $q = $q->where('u.dept_id', $authDeptId);
                } else {
                    // 部门及子部门：基于 sys_dept.tree_path 过滤
                    $deptIds = Db::name('sys_dept')
                        ->where('is_deleted', 0)
                        ->where(function ($sub) use ($authDeptId) {
                            $sub->where('id', $authDeptId)
                                ->whereOrRaw("FIND_IN_SET(?, tree_path)", [$authDeptId]);
                        })
                        ->column('id');

                    $deptIds = array_values(array_unique(array_filter(array_map('intval', $deptIds), fn($v) => $v > 0)));
                    if (!empty($deptIds)) {
                        $q = $q->whereIn('u.dept_id', $deptIds);
                    } else {
                        $q = $q->where('u.dept_id', $authDeptId);
                    }
                }
            }
        }

        if ($keywords !== '') {
            $kw = '%' . $keywords . '%';
            $q = $q->whereLike('u.username|u.nickname|u.mobile', $kw);
        }

        if ($status !== null && $status !== '') {
            $q = $q->where('u.status', (int) $status);
        }

        if ($deptId !== null && $deptId !== '' && ctype_digit((string) $deptId)) {
            $q = $q->where('u.dept_id', (int) $deptId);
        }

        if (is_array($createTime) && count($createTime) === 2) {
            $start = trim((string) ($createTime[0] ?? ''));
            $end = trim((string) ($createTime[1] ?? ''));
            if ($start !== '' && $end !== '') {
                $q = $q->whereBetweenTime('u.create_time', $start, $end);
            }
        }

        $total = (int) (clone $q)->count('u.id');

        $rows = $q
            ->field('u.id,u.username,u.nickname,u.mobile,u.gender,u.avatar,u.email,u.status,u.create_time,u.dept_id,d.name as dept_name')
            ->order('u.id', 'desc')
            ->page($pageNum, $pageSize)
            ->select()
            ->toArray();

        // 角色名称单独聚合，避免主查询过重
        $userIds = array_values(array_filter(array_map(fn($r) => (int) ($r['id'] ?? 0), $rows), fn($v) => $v > 0));
        $roleNamesMap = [];
        if (!empty($userIds)) {
            $roleRows = Db::name('sys_user_role')
                ->alias('ur')
                ->join('sys_role r', 'ur.role_id = r.id')
                ->whereIn('ur.user_id', $userIds)
                ->where('r.is_deleted', 0)
                ->field('ur.user_id,r.name')
                ->select()
                ->toArray();

            $tmp = [];
            foreach ($roleRows as $rr) {
                $uid = (int) ($rr['user_id'] ?? 0);
                $rn = (string) ($rr['name'] ?? '');
                if ($uid <= 0 || $rn === '') {
                    continue;
                }
                $tmp[$uid] ??= [];
                $tmp[$uid][] = $rn;
            }

            foreach ($tmp as $uid => $names) {
                $names = array_values(array_unique($names));
                $roleNamesMap[(int) $uid] = implode(',', $names);
            }
        }

        $list = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            $list[] = [
                'id' => (string) $id,
                'username' => $r['username'] ?? null,
                'nickname' => $r['nickname'] ?? null,
                'mobile' => $r['mobile'] ?? null,
                'gender' => isset($r['gender']) ? (int) $r['gender'] : null,
                'avatar' => $r['avatar'] ?? null,
                'email' => $r['email'] ?? null,
                'status' => isset($r['status']) ? (int) $r['status'] : null,
                'deptName' => $r['dept_name'] ?? null,
                'roleNames' => $roleNamesMap[$id] ?? '',
                'createTime' => $r['create_time'] ?? null,
            ];
        }

        return [$list, $total];
    }

    /**
     * 获取用户表单数据。
     */
    public function getUserFormData(int $userId): array
    {
        $row = Db::name('sys_user')
            ->where('id', $userId)
            ->where('is_deleted', 0)
            ->find();

        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        $roleIds = Db::name('sys_user_role')->where('user_id', $userId)->column('role_id');
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        return [
            'id' => (string) ($row['id'] ?? $userId),
            'username' => $row['username'] ?? null,
            'nickname' => $row['nickname'] ?? null,
            'mobile' => $row['mobile'] ?? null,
            'gender' => isset($row['gender']) ? (int) $row['gender'] : null,
            'avatar' => $row['avatar'] ?? null,
            'email' => $row['email'] ?? null,
            'status' => isset($row['status']) ? (int) $row['status'] : null,
            'deptId' => isset($row['dept_id']) ? (string) $row['dept_id'] : null,
            'roleIds' => $roleIds,
        ];
    }

    /**
     * 新增用户。
     */
    public function saveUser(array $data): bool
    {
        $username = trim((string) ($data['username'] ?? ''));
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $roleIds = $data['roleIds'] ?? null;

        if ($username === '' || $nickname === '' || !is_array($roleIds) || empty($roleIds)) {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $exists = Db::name('sys_user')->where('username', $username)->where('is_deleted', 0)->count();
        if ($exists > 0) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户名已存在');
        }

        $hash = password_hash('123456', PASSWORD_BCRYPT);

        Db::transaction(function () use ($data, $username, $nickname, $hash, $roleIds) {
            $now = date('Y-m-d H:i:s');

            $insert = [
                'username' => $username,
                'nickname' => $nickname,
                'mobile' => $data['mobile'] ?? null,
                'gender' => isset($data['gender']) ? (int) $data['gender'] : 1,
                'avatar' => $data['avatar'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => isset($data['status']) ? (int) $data['status'] : 1,
                'dept_id' => isset($data['deptId']) && $data['deptId'] !== '' ? (int) $data['deptId'] : null,
                'password' => $hash,
                'create_time' => $now,
                'update_time' => $now,
                'is_deleted' => 0,
            ];

            $userId = (int) Db::name('sys_user')->insertGetId($insert);

            $rows = [];
            foreach ($roleIds as $rid) {
                if (is_int($rid) || (is_string($rid) && ctype_digit($rid))) {
                    $rows[] = ['user_id' => $userId, 'role_id' => (int) $rid];
                }
            }
            if (!empty($rows)) {
                Db::name('sys_user_role')->insertAll($rows);
            }
        });

        return true;
    }

    /**
     * 修改用户状态。
     */
    public function updateUserStatus(int $userId, int $status): bool
    {
        if (!in_array($status, [0, 1], true)) {
            throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        Db::name('sys_user')->where('id', $userId)->update([
            'status' => $status,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * 修改用户。
     */
    public function updateUser(int $userId, array $data): bool
    {
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $roleIds = $data['roleIds'] ?? null;

        if ($nickname === '' || !is_array($roleIds) || empty($roleIds)) {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        Db::transaction(function () use ($userId, $data, $nickname, $roleIds) {
            $now = date('Y-m-d H:i:s');

            Db::name('sys_user')->where('id', $userId)->update([
                'nickname' => $nickname,
                'mobile' => $data['mobile'] ?? null,
                'gender' => isset($data['gender']) ? (int) $data['gender'] : null,
                'avatar' => $data['avatar'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => isset($data['status']) ? (int) $data['status'] : 1,
                'dept_id' => isset($data['deptId']) && $data['deptId'] !== '' ? (int) $data['deptId'] : null,
                'update_time' => $now,
            ]);

            Db::name('sys_user_role')->where('user_id', $userId)->delete();

            $rows = [];
            foreach ($roleIds as $rid) {
                if (is_int($rid) || (is_string($rid) && ctype_digit($rid))) {
                    $rows[] = ['user_id' => $userId, 'role_id' => (int) $rid];
                }
            }
            if (!empty($rows)) {
                Db::name('sys_user_role')->insertAll($rows);
            }
        });

        return true;
    }

    /**
     * 删除用户（批量）。
     */
    public function deleteUsers(string $ids): bool
    {
        $ids = trim($ids);
        if ($ids === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $ids)), fn($v) => $v !== ''));
        $idList = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p)) {
                throw new BusinessException(ResultCode::PARAMETER_FORMAT_MISMATCH);
            }
            $idList[] = (int) $p;
        }

        Db::name('sys_user')->whereIn('id', $idList)->update([
            'is_deleted' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * 重置指定用户密码。
     */
    public function resetUserPassword(int $userId, string $password): bool
    {
        $password = (string) $password;
        if ($password === '' || mb_strlen($password) < 6) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '密码至少需要6位字符');
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        Db::name('sys_user')->where('id', $userId)->update([
            'password' => $hash,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        $this->bumpUserSecurityVersion($userId);

        return true;
    }

    /**
     * 用户下拉选项。
     */
    public function getUserProfile(int $userId): array
    {
        $row = Db::name('sys_user')
            ->alias('u')
            ->leftJoin('sys_dept d', 'u.dept_id = d.id')
            ->where('u.id', $userId)
            ->where('u.is_deleted', 0)
            ->field('u.id,u.username,u.nickname,u.avatar,u.gender,u.mobile,u.email,u.create_time,d.name as dept_name')
            ->find();

        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        $roleNames = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->where('r.is_deleted', 0)
            ->column('r.name');
        $roleNames = array_values(array_unique(array_filter($roleNames, fn($v) => $v !== null && $v !== '')));

        return [
            'id' => (string) ($row['id'] ?? $userId),
            'username' => $row['username'] ?? null,
            'nickname' => $row['nickname'] ?? null,
            'avatar' => $row['avatar'] ?? null,
            'gender' => isset($row['gender']) ? (int) $row['gender'] : null,
            'mobile' => $row['mobile'] ?? null,
            'email' => $row['email'] ?? null,
            'deptName' => $row['dept_name'] ?? null,
            'roleNames' => implode(',', $roleNames),
            'createTime' => $row['create_time'] ?? null,
        ];
    }

    /**
     * 个人中心修改用户信息。
     */
    public function updateUserProfile(int $userId, array $data): bool
    {
        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        $updates = [];
        if (array_key_exists('nickname', $data)) {
            $updates['nickname'] = trim((string) $data['nickname']);
        }
        if (array_key_exists('avatar', $data)) {
            $updates['avatar'] = trim((string) $data['avatar']);
        }
        if (array_key_exists('gender', $data)) {
            $gender = $data['gender'];
            $updates['gender'] = $gender === null || $gender === '' ? null : (int) $gender;
        }

        if (empty($updates)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '请至少修改一项');
        }

        $updates['update_time'] = date('Y-m-d H:i:s');
        Db::name('sys_user')->where('id', $userId)->update($updates);

        return true;
    }

    /**
     * 当前用户修改密码。
     */
    public function changeCurrentUserPassword(int $userId, array $data): bool
    {
        $oldPassword = (string) ($data['oldPassword'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');
        $confirmPassword = (string) ($data['confirmPassword'] ?? '');

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        if ($newPassword !== $confirmPassword) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '两次输入的密码不一致');
        }

        if (mb_strlen($newPassword) < 6) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '密码至少需要6位字符');
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        $hash = (string) ($row['password'] ?? '');
        if ($hash === '' || !password_verify($oldPassword, $hash)) {
            throw new BusinessException(ResultCode::USER_PASSWORD_ERROR);
        }

        if (password_verify($newPassword, $hash)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '新密码不能与原密码相同');
        }

        Db::name('sys_user')->where('id', $userId)->update([
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        $this->bumpUserSecurityVersion($userId);

        return true;
    }

    /**
     * 发送短信验证码（绑定或更换手机号）。
     */
    public function sendMobileCode(string $mobile): bool
    {
        $mobile = trim($mobile);
        if ($mobile === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $code = (string) random_int(100000, 999999);
        $key = RedisKey::format('captcha:mobile:{}', $mobile);
        RedisClient::get()->setex($key, 300, $code);
        return true;
    }

    /**
     * 绑定或更换手机号。
     */
    public function bindOrChangeMobile(int $userId, array $data): bool
    {
        $mobile = trim((string) ($data['mobile'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($mobile === '' || $code === '' || $password === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }
        $hash = (string) ($row['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '当前密码错误');
        }

        $key = RedisKey::format('captcha:mobile:{}', $mobile);
        $cached = (string) (RedisClient::get()->get($key) ?? '');
        if ($cached === '' || $cached !== $code) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        $exists = Db::name('sys_user')
            ->where('mobile', $mobile)
            ->where('is_deleted', 0)
            ->where('id', '<>', $userId)
            ->find();
        if ($exists) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '手机号已被其他账号绑定');
        }

        Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->update([
            'mobile' => $mobile,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        RedisClient::get()->del([$key]);
        return true;
    }

    /**
     * 发送邮箱验证码（绑定或更换邮箱）。
     */
    public function sendEmailCode(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $code = (string) random_int(100000, 999999);
        $key = RedisKey::format('captcha:email:{}', $email);
        RedisClient::get()->setex($key, 300, $code);
        return true;
    }

    /**
     * 绑定或更换邮箱。
     */
    public function bindOrChangeEmail(int $userId, array $data): bool
    {
        $email = trim((string) ($data['email'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($email === '' || $code === '' || $password === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }
        $hash = (string) ($row['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '当前密码错误');
        }

        $key = RedisKey::format('captcha:email:{}', $email);
        $cached = (string) (RedisClient::get()->get($key) ?? '');
        if ($cached === '' || $cached !== $code) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        $exists = Db::name('sys_user')
            ->where('email', $email)
            ->where('is_deleted', 0)
            ->where('id', '<>', $userId)
            ->find();
        if ($exists) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '邮箱已被其他账号绑定');
        }

        Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->update([
            'email' => $email,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        RedisClient::get()->del([$key]);
        return true;
    }

    public function unbindMobile(int $userId, array $data): bool
    {
        $password = (string) ($data['password'] ?? '');
        if ($password === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        $mobile = trim((string) ($row['mobile'] ?? ''));
        if ($mobile === '') {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '当前账号未绑定手机号');
        }

        $hash = (string) ($row['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '当前密码错误');
        }

        Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->update([
            'mobile' => null,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function unbindEmail(int $userId, array $data): bool
    {
        $password = (string) ($data['password'] ?? '');
        if ($password === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        $row = Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '用户不存在');
        }

        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '') {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '当前账号未绑定邮箱');
        }

        $hash = (string) ($row['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            throw new BusinessException(ResultCode::INVALID_USER_INPUT, '当前密码错误');
        }

        Db::name('sys_user')->where('id', $userId)->where('is_deleted', 0)->update([
            'email' => null,
            'update_time' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * 生成用户导入模板（xlsx）。
     */
    public function buildUserImportTemplateXlsx(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['用户名', '昵称', '性别', '手机号码', '邮箱', '角色', '部门'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }

        $sheet->setCellValue('A2', 'demo');
        $sheet->setCellValue('B2', '演示用户');
        $sheet->setCellValue('C2', '男');
        $sheet->setCellValue('D2', '13800138000');
        $sheet->setCellValue('E2', 'demo@example.com');
        $sheet->setCellValue('F2', 'ADMIN');
        $sheet->setCellValue('G2', 'ROOT');

        return $this->writeSpreadsheetToString($spreadsheet);
    }

    /**
     * 导出用户（xlsx）。
     */
    public function exportUsersXlsx(array $queryParams, ?array $authUser = null): string
    {
        [$list] = $this->getUserPage($queryParams, $authUser);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['用户名', '用户昵称', '部门', '性别', '手机号码', '邮箱', '创建时间'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }

        $genderMap = $this->getGenderLabelMap();

        $rowNum = 2;
        foreach ($list as $u) {
            $username = (string) ($u['username'] ?? '');
            $nickname = (string) ($u['nickname'] ?? '');
            $deptName = (string) ($u['deptName'] ?? '');
            $gender = $u['gender'] ?? null;
            $genderLabel = $gender === null ? '' : ($genderMap[(string) $gender] ?? (string) $gender);
            $mobile = (string) ($u['mobile'] ?? '');
            $email = (string) ($u['email'] ?? '');
            $createTime = (string) ($u['createTime'] ?? '');

            $sheet->setCellValue('A' . $rowNum, $username);
            $sheet->setCellValue('B' . $rowNum, $nickname);
            $sheet->setCellValue('C' . $rowNum, $deptName);
            $sheet->setCellValue('D' . $rowNum, $genderLabel);
            $sheet->setCellValue('E' . $rowNum, $mobile);
            $sheet->setCellValue('F' . $rowNum, $email);
            $sheet->setCellValue('G' . $rowNum, $createTime);
            $rowNum++;
        }

        return $this->writeSpreadsheetToString($spreadsheet);
    }

    /**
     * 导入用户（xlsx）。
     */
    public function importUsersFromXlsx(mixed $file, mixed $deptId = null): array
    {
        $path = null;
        if (is_object($file) && method_exists($file, 'getPathname')) {
            $path = (string) $file->getPathname();
        }
        if ($path === null || $path === '') {
            throw new BusinessException(ResultCode::UPLOAD_FILE_EXCEPTION);
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $excelResult = [
            'code' => ResultCode::SUCCESS->getCode(),
            'validCount' => 0,
            'invalidCount' => 0,
            'messageList' => [],
        ];

        if (count($rows) <= 1) {
            return $excelResult;
        }

        $deptId = $deptId === null || $deptId === '' ? null : (int) $deptId;
        $roleCodeToId = $this->getRoleCodeToIdMap();
        $deptCodeToId = $this->getDeptCodeToIdMap();
        $genderLabelToValue = $this->getGenderLabelToValueMap();

        $currentRow = 2;
        foreach ($rows as $idx => $r) {
            if ($idx === 1) {
                continue;
            }

            $username = trim((string) ($r['A'] ?? ''));
            $nickname = trim((string) ($r['B'] ?? ''));
            $genderLabel = trim((string) ($r['C'] ?? ''));
            $mobile = trim((string) ($r['D'] ?? ''));
            $email = trim((string) ($r['E'] ?? ''));
            $roleCodes = trim((string) ($r['F'] ?? ''));
            $deptCode = trim((string) ($r['G'] ?? ''));

            $validation = true;
            $errorMsg = '第' . $currentRow . '行数据校验失败：';

            if ($username === '') {
                $errorMsg .= '用户名为空；';
                $validation = false;
            } else {
                $exists = Db::name('sys_user')->where('username', $username)->where('is_deleted', 0)->count();
                if ($exists > 0) {
                    $errorMsg .= '用户名已存在；';
                    $validation = false;
                }
            }

            if ($nickname === '') {
                $errorMsg .= '用户昵称为空；';
                $validation = false;
            }

            if ($mobile === '') {
                $errorMsg .= '手机号码为空；';
                $validation = false;
            } elseif (!preg_match('/^1\d{10}$/', $mobile)) {
                $errorMsg .= '手机号码不正确；';
                $validation = false;
            }

            $genderValue = null;
            if ($genderLabel !== '') {
                $genderValue = $genderLabelToValue[$genderLabel] ?? null;
            }

            $deptIdValue = null;
            if ($deptCode !== '') {
                $deptIdValue = $deptCodeToId[$deptCode] ?? null;
                if ($deptIdValue === null) {
                    $errorMsg .= '部门不存在；';
                    $validation = false;
                }
            } elseif ($deptId !== null) {
                $deptIdValue = $deptId;
            }

            $roleIds = [];
            if ($roleCodes !== '') {
                $codes = array_values(array_filter(array_map('trim', explode(',', $roleCodes)), fn($v) => $v !== ''));
                foreach ($codes as $code) {
                    if (isset($roleCodeToId[$code])) {
                        $roleIds[] = (int) $roleCodeToId[$code];
                    }
                }
                $roleIds = array_values(array_unique($roleIds));
            }

            if ($validation) {
                try {
                    Db::transaction(function () use ($username, $nickname, $genderValue, $mobile, $email, $deptIdValue, $roleIds) {
                        $now = date('Y-m-d H:i:s');
                        $hash = password_hash('123456', PASSWORD_BCRYPT);

                        $userId = (int) Db::name('sys_user')->insertGetId([
                            'username' => $username,
                            'nickname' => $nickname,
                            'gender' => $genderValue,
                            'mobile' => $mobile,
                            'email' => $email !== '' ? $email : null,
                            'dept_id' => $deptIdValue,
                            'status' => 1,
                            'avatar' => null,
                            'password' => $hash,
                            'create_time' => $now,
                            'update_time' => $now,
                            'is_deleted' => 0,
                        ]);

                        if (!empty($roleIds)) {
                            $rows = [];
                            foreach ($roleIds as $rid) {
                                $rows[] = ['user_id' => $userId, 'role_id' => (int) $rid];
                            }
                            Db::name('sys_user_role')->insertAll($rows);
                        }
                    });

                    $excelResult['validCount']++;
                } catch (\Throwable $e) {
                    $excelResult['invalidCount']++;
                    $excelResult['messageList'][] = $errorMsg . '保存失败；';
                }
            } else {
                $excelResult['invalidCount']++;
                $excelResult['messageList'][] = $errorMsg;
            }

            $currentRow++;
        }

        return $excelResult;
    }

    /**
     * 输出 xlsx 为二进制字符串。
     */
    private function writeSpreadsheetToString(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $bin = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        return $bin;
    }

    private function bumpUserSecurityVersion(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $keys = (array) (config('security.redis.keys') ?? []);
        $pattern = (string) ($keys['user_security_version'] ?? 'auth:user:security_version:{}');
        $key = RedisKey::format($pattern, $userId);

        $redis = RedisClient::get();
        $current = (int) ($redis->get($key) ?: 1);
        $next = $current + 1;
        $redis->set($key, (string) $next);
    }

    /**
     * 性别字典：label -> value。
     */
    private function getGenderLabelToValueMap(): array
    {
        $rows = Db::name('sys_dict_item')
            ->where('dict_code', 'gender')
            ->where('is_deleted', 0)
            ->field('label,value')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $r) {
            $label = (string) ($r['label'] ?? '');
            $value = (string) ($r['value'] ?? '');
            if ($label !== '' && $value !== '') {
                $map[$label] = (int) $value;
            }
        }
        return $map;
    }

    /**
     * 性别字典：value -> label。
     */
    private function getGenderLabelMap(): array
    {
        $rows = Db::name('sys_dict_item')
            ->where('dict_code', 'gender')
            ->where('is_deleted', 0)
            ->field('label,value')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $r) {
            $label = (string) ($r['label'] ?? '');
            $value = (string) ($r['value'] ?? '');
            if ($label !== '' && $value !== '') {
                $map[$value] = $label;
            }
        }
        return $map;
    }

    /**
     * 角色编码 -> 角色ID。
     */
    private function getRoleCodeToIdMap(): array
    {
        $rows = Db::name('sys_role')
            ->where('is_deleted', 0)
            ->field('id,code')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $r) {
            $code = (string) ($r['code'] ?? '');
            $id = (int) ($r['id'] ?? 0);
            if ($code !== '' && $id > 0) {
                $map[$code] = $id;
            }
        }
        return $map;
    }

    /**
     * 部门编码 -> 部门ID。
     */
    private function getDeptCodeToIdMap(): array
    {
        $rows = Db::name('sys_dept')
            ->where('is_deleted', 0)
            ->field('id,code')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $r) {
            $code = (string) ($r['code'] ?? '');
            $id = (int) ($r['id'] ?? 0);
            if ($code !== '' && $id > 0) {
                $map[$code] = $id;
            }
        }
        return $map;
    }
}
