<?php declare(strict_types=1);

namespace app\system\model;

use app\common\model\BaseModel;

/**
 * 操作日志模型
 *
 * @property int    $id            日志ID
 * @property int    $module        模块
 * @property int    $actionType    操作类型
 * @property string $title         操作标题
 * @property string $content       自定义日志内容
 * @property int    $operatorId    操作人ID
 * @property string $operatorName  操作人名称
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
 * @property string $createTime    创建时间
 */
class Log extends BaseModel
{
    protected $name = 'sys_log';

    protected $type = [
        'id' => 'integer',
        'module' => 'integer',
        'action_type' => 'integer',
        'operator_id' => 'integer',
        'status' => 'integer',
        'execution_time' => 'integer',
    ];

    protected $deleteTime = false;

    public function getOperatorIdAttr(mixed $value): string
    {
        return (string) $value;
    }
}
