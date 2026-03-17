<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\Model;

/**
 * 操作日志模型
 *
 * @property int    $id          日志ID
 * @property string $module      模块
 * @property string $action      操作
 * @property string $method      请求方法
 * @property string $url         请求地址
 * @property string $ip          IP地址
 * @property string $userAgent   UA
 * @property string $params      请求参数
 * @property string $result      执行结果
 * @property int    $userId      操作用户ID
 * @property string $username    操作用户名
 * @property int    $executeTime 执行耗时(ms)
 * @property string $createTime  创建时间
 */
class Log extends Model
{
    protected $name = 'sys_log';

    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'execute_time' => 'integer',
    ];

    // 无软删除
    protected $deleteTime = false;

    /**
     * 用户ID访问器
     */
    public function getUserIdAttr(mixed $value): string
    {
        return (string) $value;
    }
}
