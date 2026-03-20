<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\Model;

/**
 * 操作日志模型
 *
 * @property int    $id            日志ID
 * @property string $actionType    行为类型
 * @property string $requestUri    请求地址
 * @property string $requestMethod 请求方式
 * @property string $ip            IP地址
 * @property string $province      省份
 * @property string $city          城市
 * @property string $device        设备
 * @property string $os            操作系统
 * @property string $browser       浏览器
 * @property int    $status        状态：0失败 1成功
 * @property string $errorMsg      错误信息
 * @property int    $executionTime 执行耗时(ms)
 * @property int    $createBy      操作用户ID
 * @property string $createTime    创建时间
 */
class Log extends Model
{
    protected $name = 'sys_log';

    protected $type = [
        'id' => 'integer',
        'create_by' => 'integer',
        'status' => 'integer',
        'execution_time' => 'integer',
    ];

    protected $deleteTime = false;

    public function getCreateByAttr(mixed $value): string
    {
        return (string) $value;
    }
}
