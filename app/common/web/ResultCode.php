<?php

declare(strict_types=1);

namespace app\common\web;

enum ResultCode: string implements IResultCode
{
    case SUCCESS = '00000';

    case USER_ERROR = 'A0001';

    case USER_REGISTRATION_ERROR = 'A0100';
    case USER_NOT_AGREE_PRIVACY_AGREEMENT = 'A0101';

    case VERIFICATION_CODE_INPUT_ERROR = 'A0130';

    case USER_LOGIN_EXCEPTION = 'A0200';
    case ACCOUNT_NOT_FOUND = 'A0201';
    case ACCOUNT_FROZEN = 'A0202';
    case USER_PASSWORD_ERROR = 'A0210';

    case ACCESS_TOKEN_INVALID = 'A0230';
    case REFRESH_TOKEN_INVALID = 'A0231';

    case USER_VERIFICATION_CODE_ERROR = 'A0240';
    case USER_VERIFICATION_CODE_ATTEMPT_LIMIT_EXCEEDED = 'A0241';
    case USER_VERIFICATION_CODE_EXPIRED = 'A0242';

    case ACCESS_PERMISSION_EXCEPTION = 'A0300';
    case ACCESS_UNAUTHORIZED = 'A0301';

    case USER_REQUEST_PARAMETER_ERROR = 'A0400';
    case INVALID_USER_INPUT = 'A0402';
    case REQUEST_REQUIRED_PARAMETER_IS_EMPTY = 'A0410';
    case PARAMETER_FORMAT_MISMATCH = 'A0421';

    case USER_REQUEST_SERVICE_EXCEPTION = 'A0500';
    case REQUEST_CONCURRENCY_LIMIT_EXCEEDED = 'A0502';
    case DUPLICATE_SUBMISSION = 'A0506';

    case UPLOAD_FILE_EXCEPTION = 'A0700';
    case DELETE_FILE_EXCEPTION = 'A0710';

    case SYSTEM_ERROR = 'B0001';
    case SYSTEM_EXECUTION_TIMEOUT = 'B0100';

    case THIRD_PARTY_SERVICE_ERROR = 'C0001';
    case INTERFACE_NOT_EXIST = 'C0113';
    case DATABASE_SERVICE_ERROR = 'C0300';
    case DATABASE_EXECUTION_SYNTAX_ERROR = 'C0313';
    case INTEGRITY_CONSTRAINT_VIOLATION = 'C0342';
    case DATABASE_ACCESS_DENIED = 'C0351';

    public function getCode(): string
    {
        return $this->value;
    }

    public function getMsg(): string
    {
        return match ($this) {
            self::SUCCESS => '成功',

            self::USER_ERROR => '用户端错误',

            self::USER_REGISTRATION_ERROR => '用户注册错误',
            self::USER_NOT_AGREE_PRIVACY_AGREEMENT => '用户未同意隐私协议',

            self::VERIFICATION_CODE_INPUT_ERROR => '校验码输入错误',

            self::USER_LOGIN_EXCEPTION => '用户登录异常',
            self::ACCOUNT_NOT_FOUND => '用户账户不存在',
            self::ACCOUNT_FROZEN => '用户账户被冻结',
            self::USER_PASSWORD_ERROR => '用户名或密码错误',

            self::ACCESS_TOKEN_INVALID => '访问令牌无效或已过期',
            self::REFRESH_TOKEN_INVALID => '刷新令牌无效或已过期',

            self::USER_VERIFICATION_CODE_ERROR => '验证码错误',
            self::USER_VERIFICATION_CODE_ATTEMPT_LIMIT_EXCEEDED => '用户验证码尝试次数超限',
            self::USER_VERIFICATION_CODE_EXPIRED => '用户验证码过期',

            self::ACCESS_PERMISSION_EXCEPTION => '访问权限异常',
            self::ACCESS_UNAUTHORIZED => '访问未授权',

            self::USER_REQUEST_PARAMETER_ERROR => '用户请求参数错误',
            self::INVALID_USER_INPUT => '无效的用户输入',
            self::REQUEST_REQUIRED_PARAMETER_IS_EMPTY => '请求必填参数为空',
            self::PARAMETER_FORMAT_MISMATCH => '参数格式不匹配',

            self::USER_REQUEST_SERVICE_EXCEPTION => '用户请求服务异常',
            self::REQUEST_CONCURRENCY_LIMIT_EXCEEDED => '请求并发数超出限制',
            self::DUPLICATE_SUBMISSION => '请勿重复提交',

            self::UPLOAD_FILE_EXCEPTION => '上传文件异常',
            self::DELETE_FILE_EXCEPTION => '删除文件异常',

            self::SYSTEM_ERROR => '系统执行出错',
            self::SYSTEM_EXECUTION_TIMEOUT => '系统执行超时',

            self::THIRD_PARTY_SERVICE_ERROR => '调用第三方服务出错',
            self::INTERFACE_NOT_EXIST => '接口不存在',
            self::DATABASE_SERVICE_ERROR => '数据库服务出错',
            self::DATABASE_EXECUTION_SYNTAX_ERROR => '数据库执行语法错误',
            self::INTEGRITY_CONSTRAINT_VIOLATION => '违反了完整性约束',
            self::DATABASE_ACCESS_DENIED => '演示环境已禁用数据库写入功能，请本地部署修改数据库链接或开启Mock模式进行体验',
        };
    }

    public static function fromCode(string $code): self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $code) {
                return $case;
            }
        }

        return self::SYSTEM_ERROR;
    }
}
