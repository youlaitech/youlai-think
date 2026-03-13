<?php declare(strict_types=1);

namespace app\Auth\Service;

use app\common\exception\BusinessException;
use app\common\model\SysUserSocial;
use app\common\redis\RedisClient;
use app\common\security\JwtTokenManager;
use app\common\web\ResultCode;
use app\System\Model\User;
use GuzzleHttp\Client;
use think\facade\Db;
use think\facade\Log;

/**
 * 微信小程序认证服务
 */
final class WechatMiniappAuthService
{
    private const string JS_CODE_2_SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session?appid=%s&secret=%s&js_code=%s&grant_type=authorization_code';
    private const string GET_PHONE_NUMBER_URL = 'https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token=%s&code=%s';
    private const string GET_ACCESS_TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s';

    private JwtTokenManager $jwt;
    private Client $httpClient;
    private array $config;

    public function __construct(JwtTokenManager $jwt)
    {
        $this->jwt = $jwt;
        $this->httpClient = new Client(['timeout' => 10]);
        $this->config = [
            'appId' => config('wechat.miniapp.app_id', ''),
            'appSecret' => config('wechat.miniapp.app_secret', ''),
        ];
    }

    /**
     * 静默登录
     */
    public function silentLogin(string $code): array
    {
        $session = $this->getSession($code);
        $openId = $session['openid'] ?? '';

        if (empty($openId)) {
            throw new BusinessException(ResultCode::USER_LOGIN_ERROR, '微信登录失败：无法获取用户标识');
        }

        // 查找是否已绑定用户
        $social = SysUserSocial::where('platform', 'WECHAT_MINI')
            ->where('openid', $openId)
            ->find();

        if ($social) {
            // 已绑定用户，直接登录
            $token = $this->generateTokenByUserId((int) $social->user_id);
            return [
                'needBindMobile' => false,
                'accessToken' => $token['accessToken'],
                'refreshToken' => $token['refreshToken'],
                'tokenType' => $token['tokenType'],
                'expiresIn' => $token['expiresIn'],
            ];
        }

        // 未绑定用户，返回需要绑定手机号
        Log::info("微信小程序静默登录：用户未绑定手机号，openId={$openId}");
        return [
            'needBindMobile' => true,
            'openId' => $openId,
        ];
    }

    /**
     * 手机号快捷登录
     */
    public function phoneLogin(string $loginCode, string $phoneCode): array
    {
        // 获取微信会话信息
        $session = $this->getSession($loginCode);
        $openId = $session['openid'] ?? '';

        // 获取手机号
        $mobile = $this->getPhoneNumber($phoneCode);

        Log::info("微信小程序手机号快捷登录：openId={$openId}, mobile={$mobile}");

        // 查询或创建用户
        $user = $this->findOrCreateUser($mobile);

        // 绑定微信 openid
        $this->bindWechatOpenId((int) $user->id, $openId, $session['unionid'] ?? null, $session['session_key'] ?? null);

        // 生成认证令牌
        return $this->generateTokenByUserId((int) $user->id);
    }

    /**
     * 绑定手机号
     */
    public function bindMobile(string $openId, string $mobile, string $smsCode): array
    {
        // 验证短信验证码
        $this->validateSmsCode($mobile, $smsCode);

        // 查询或创建用户
        $user = $this->findOrCreateUser($mobile);

        // 绑定微信 openid
        $this->bindWechatOpenId((int) $user->id, $openId, null, null);

        Log::info("微信小程序绑定手机号成功：mobile={$mobile}, openId={$openId}");

        // 生成认证令牌
        return $this->generateTokenByUserId((int) $user->id);
    }

    /**
     * 获取微信会话信息
     */
    private function getSession(string $code): array
    {
        $url = sprintf(self::JS_CODE_2_SESSION_URL, $this->config['appId'], $this->config['appSecret'], $code);

        try {
            $response = $this->httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['errcode']) && $data['errcode'] != 0) {
                $errMsg = $data['errmsg'] ?? 'Unknown error';
                Log::error("获取微信会话信息失败：code={$code}, errcode={$data['errcode']}, errmsg={$errMsg}");
                throw new BusinessException(ResultCode::USER_LOGIN_ERROR, "微信登录失败：{$errMsg}");
            }

            return $data;
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("获取微信会话信息失败：code={$code}, error={$e->getMessage()}");
            throw new BusinessException(ResultCode::USER_LOGIN_ERROR, '微信登录失败：' . $e->getMessage());
        }
    }

    /**
     * 获取微信手机号
     */
    private function getPhoneNumber(string $phoneCode): string
    {
        $accessToken = $this->getAccessToken();
        $url = sprintf(self::GET_PHONE_NUMBER_URL, $accessToken, $phoneCode);

        try {
            $response = $this->httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['errcode'] != 0) {
                $errMsg = $data['errmsg'] ?? 'Unknown error';
                Log::error("获取微信手机号失败：phoneCode={$phoneCode}, errcode={$data['errcode']}, errmsg={$errMsg}");
                throw new BusinessException(ResultCode::USER_LOGIN_ERROR, "获取手机号失败：{$errMsg}");
            }

            return $data['phone_info']['phoneNumber'] ?? throw new BusinessException(ResultCode::USER_LOGIN_ERROR, '获取手机号失败');
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("获取微信手机号失败：phoneCode={$phoneCode}, error={$e->getMessage()}");
            throw new BusinessException(ResultCode::USER_LOGIN_ERROR, '获取手机号失败：' . $e->getMessage());
        }
    }

    /**
     * 获取微信 AccessToken
     */
    private function getAccessToken(): string
    {
        $redis = RedisClient::get();
        $cacheKey = "wechat:access_token:{$this->config['appId']}";

        // 先从缓存获取
        $cached = $redis->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // 请求新 token
        $url = sprintf(self::GET_ACCESS_TOKEN_URL, $this->config['appId'], $this->config['appSecret']);

        $response = $this->httpClient->get($url);
        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['errcode']) && $data['errcode'] != 0) {
            $errMsg = $data['errmsg'] ?? 'Unknown error';
            throw new BusinessException(ResultCode::USER_LOGIN_ERROR, "获取微信AccessToken失败：{$errMsg}");
        }

        // 缓存 token（提前5分钟过期）
        $expiresIn = max(($data['expires_in'] ?? 7200) - 300, 60);
        $redis->setex($cacheKey, $expiresIn, $data['access_token']);

        return $data['access_token'];
    }

    /**
     * 查询或创建用户
     */
    private function findOrCreateUser(string $mobile): User
    {
        $user = User::where('mobile', $mobile)->where('is_deleted', 0)->find();

        if ($user) {
            return $user;
        }

        // 创建新用户
        Db::startTrans();
        try {
            $user = new User();
            $user->username = 'wx_' . substr(md5(uniqid()), 0, 8);
            $user->nickname = '微信用户';
            $user->mobile = $mobile;
            $user->status = 1;
            $user->is_deleted = 0;
            $user->save();

            // 分配 GUEST 角色（角色ID=3）
            Db::table('sys_user_role')->insert([
                'user_id' => $user->id,
                'role_id' => 3,
            ]);

            Db::commit();
            Log::info("微信小程序登录：创建新用户，mobile={$mobile}, userId={$user->id}");
            return $user;
        } catch (\Exception $e) {
            Db::rollback();
            throw new BusinessException(ResultCode::USER_ERROR, '创建用户失败：' . $e->getMessage());
        }
    }

    /**
     * 绑定微信 openid
     */
    private function bindWechatOpenId(int $userId, string $openId, ?string $unionId, ?string $sessionKey): void
    {
        try {
            $existing = SysUserSocial::where('platform', 'WECHAT_MINI')
                ->where('openid', $openId)
                ->find();

            if ($existing) {
                // 更新绑定
                $existing->user_id = $userId;
                $existing->unionid = $unionId;
                $existing->session_key = $sessionKey;
                $existing->save();
            } else {
                // 新增绑定
                $social = new SysUserSocial();
                $social->user_id = $userId;
                $social->platform = 'WECHAT_MINI';
                $social->openid = $openId;
                $social->unionid = $unionId;
                $social->session_key = $sessionKey;
                $social->verified = 1;
                $social->save();
            }
        } catch (\Exception $e) {
            // 绑定失败不影响登录
            Log::warning("绑定微信 openid 失败：userId={$userId}, openId={$openId}, error={$e->getMessage()}");
        }
    }

    /**
     * 验证短信验证码
     */
    private function validateSmsCode(string $mobile, string $smsCode): void
    {
        $redis = RedisClient::get();
        $cacheKey = "sms:login:{$mobile}";
        $cached = $redis->get($cacheKey);

        if (!$cached) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_EXPIRED);
        }

        if ($cached !== $smsCode) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        // 验证成功后删除验证码
        $redis->del($cacheKey);
    }

    /**
     * 根据用户ID生成Token
     */
    private function generateTokenByUserId(int $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            throw new BusinessException(ResultCode::USER_ERROR, '用户不存在');
        }

        $userAuthInfo = [
            'userId' => $userId,
            'deptId' => $user->dept_id ?? null,
        ];

        $token = $this->jwt->generateToken($userAuthInfo);

        return [
            'accessToken' => $token->accessToken,
            'refreshToken' => $token->refreshToken,
            'tokenType' => $token->tokenType,
            'expiresIn' => $token->expiresIn,
        ];
    }
}
