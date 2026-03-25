<?php declare(strict_types=1);

namespace app\system\repository;

use app\common\model\BaseModel;
use app\system\model\User;
use think\facade\Db;

/**
 * 用户仓储层
 *
 * 封装数据访问逻辑，负责与数据库交互
 */
class UserRepository
{
    /**
     * 根据用户名查询
     */
    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->find();
    }

    /**
     * 根据 ID 查询
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * 分页查询
     */
    public function paginate(array $params, int $page = 1, int $pageSize = 10): array
    {
        $query = User::where('is_deleted', 0);

        $keywords = $params['keywords'] ?? '';
        if ($keywords !== '') {
            $query->whereLike('username|nickname', "%{$keywords}%");
        }

        $total = $query->count();
        $list = $query->page($page, $pageSize)->select();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 创建用户
     */
    public function create(array $data): int
    {
        $user = new User();
        $user->save($data);
        return (int) $user->id;
    }

    /**
     * 更新用户
     */
    public function update(int $id, array $data): bool
    {
        return User::update($data, ['id' => $id]) !== false;
    }

    /**
     * 删除用户
     */
    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}
