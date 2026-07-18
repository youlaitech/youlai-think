<?php declare(strict_types=1);

namespace app\auth\controller;

use app\common\constants\RedisKey;
use app\common\exception\BusinessException;
use extend\redis\RedisClient;
use app\common\web\ResultCode;
use app\controller\BaseController;
use app\auth\service\AuthService;
use app\system\annotation\Log;
use app\system\enums\ActionType;
use app\system\model\Log as LogModel;
use Gregwar\Captcha\CaptchaBuilder;
use OpenApi\Annotations as OA;
use think\response\Json;

/**
 * @OA\Tag(name="01.认证中心")
 */
final class AuthController extends BaseController
{
    /**
     * 获取验证码
     *
     * @OA\Get(
     *     path="/api/v1/auth/captcha",
     *     summary="获取验证码",
     *     tags={"01.认证中心"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function captcha(): Json
    {
        $length = (int) config('captcha.length', 4);
        $length = $length > 0 ? $length : 4;

        $chars = (string) config('captcha.codeSet', '23456789ABCDEFGHJKLMNPQRSTUVWXYZ');
        if ($chars === '') {
            $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        }

        $imageW = (int) config('captcha.imageW', 100);
        $imageH = (int) config('captcha.imageH', 32);
        $imageW = $imageW > 0 ? $imageW : 100;
        $imageH = $imageH > 0 ? $imageH : 32;

        $bg = config('captcha.bg', [255, 255, 255]);

        $expire = (int) config('captcha.expire', 300);
        $expire = $expire > 0 ? $expire : 300;

        $phrase = '';
        $charsLen = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $phrase .= $chars[random_int(0, $charsLen - 1)];
        }

        $builder = new CaptchaBuilder($phrase);
        if (is_array($bg) && count($bg) === 3) {
            $builder->setBackgroundColor((int) $bg[0], (int) $bg[1], (int) $bg[2]);
        }
        $builder->build($imageW, $imageH);

        $captchaId = uniqid('', true);
        RedisClient::get()->setex(RedisKey::captcha($captchaId), $expire, strtolower($phrase));
        $base64 = $builder->inline();

        return $this->success([
            'captcha_id' => $captchaId,
            'captcha_base64' => $base64,
        ]);
    }

    /**
     * 登录
     *
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="账号密码登录",
     *     tags={"01.认证中心"},
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function login(): Json
    {
        $username = $this->getParam('username', '');
        $password = $this->getParam('password', '');
        $captchaId = $this->getParam('captcha_id', '');
        $captchaCode = $this->getParam('captcha_code', '');

        // 验证码校验
        $this->validateCaptcha($captchaId, $captchaCode);

        // 执行登录
        $result = $this->service(AuthService::class)->login($username, $password);

        return $this->success($result, '登录成功');
    }

    /**
     * 登出
     *
     * @OA\Delete(
     *     path="/api/v1/auth/logout",
     *     summary="登出",
     *     tags={"01.认证中心"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::LOGOUT)]
    public function logout(): Json
    {
        $accessToken = $this->request->header('Authorization', '');

        $this->service(AuthService::class)->logout($accessToken ?: null, null);

        return $this->success(null, '登出成功');
    }

    /**
     * 刷新 Token
     *
     * @OA\Post(
     *     path="/api/v1/auth/refresh-token",
     *     summary="刷新令牌",
     *     tags={"01.认证中心"},
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function refresh(): Json
    {
        $refreshToken = $this->getParam('refresh_token', '');

        $result = $this->service(AuthService::class)->refresh($refreshToken);

        return $this->success($result);
    }

    /**
     * 发送登录短信验证码
     *
     * @OA\Post(
     *     path="/api/v1/auth/sms/code",
     *     summary="发送登录短信验证码",
     *     tags={"01.认证中心"},
     *     @OA\Parameter(name="mobile", in="query", description="手机号", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function sendLoginVerifyCode(): Json
    {
        $mobile = $this->getParam('mobile', '');

        if (empty($mobile)) {
            return $this->fail('A0400', '手机号不能为空');
        }

        // 同一手机号 60 秒内仅允许发送一次验证码，防止短信轰炸
        $smsKey = "rate_limit:api:sms:{$mobile}";
        $redis = RedisClient::get();
        if ($redis->exists($smsKey)) {
            return json(Result::failedWith(ResultCode::REQUEST_CONCURRENCY_LIMIT_EXCEEDED)->toArray(), 429);
        }
        $redis->setex($smsKey, 60, '1');

        $this->service(AuthService::class)->sendSmsLoginCode($mobile);

        return $this->success(null, '验证码发送成功');
    }

    /**
     * 短信验证码登录
     *
     * @OA\Post(
     *     path="/api/v1/auth/login/sms",
     *     summary="短信验证码登录",
     *     tags={"01.认证中心"},
     *     @OA\Parameter(name="mobile", in="query", description="手机号", required=true),
     *     @OA\Parameter(name="code", in="query", description="验证码", required=true),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    #[Log(actionType: ActionType::LOGIN)]
    public function loginBySms(): Json
    {
        $mobile = $this->getParam('mobile', '');
        $code = $this->getParam('code', '');

        if (empty($mobile) || empty($code)) {
            return $this->fail('A0400', '手机号和验证码不能为空');
        }

        $result = $this->service(AuthService::class)->loginBySms($mobile, $code);

        // 手动记录登录日志（登录接口是公开的，LogMiddleware 无法获取 userId）
        $this->recordLoginLogByMobile($mobile, '/api/v1/auth/login/sms');

        return $this->success($result, '登录成功');
    }

    /**
     * 验证验证码
     */
    private function validateCaptcha(string $captchaId, string $captchaCode): void
    {
        if (empty($captchaId) || empty($captchaCode)) {
            throw new BusinessException(ResultCode::USER_REQUEST_PARAMETER_ERROR, '验证码不能为空');
        }

        $redis = RedisClient::get();
        $key = RedisKey::captcha($captchaId);
        $storedCode = $redis->get($key);
        if (!$storedCode || strtolower((string) $storedCode) !== strtolower($captchaCode)) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        $redis->del($key);
    }

    /**
     * 手动记录用户名密码登录日志
     */
    private function recordLoginLog(string $username, string $requestUri): void
    {
        try {
            $user = \app\system\model\User::where('username', $username)->find();
            if (!$user) return;

            $this->saveLoginLog((int) $user->id, $requestUri);
        } catch (\Throwable) {
            // 日志记录失败不影响登录
        }
    }

    /**
     * 手动记录短信验证码登录日志
     */
    private function recordLoginLogByMobile(string $mobile, string $requestUri): void
    {
        try {
            $user = \app\system\model\User::where('mobile', $mobile)->find();
            if (!$user) return;

            $this->saveLoginLog((int) $user->id, $requestUri);
        } catch (\Throwable) {
            // 日志记录失败不影响登录
        }
    }

    /**
     * 保存登录日志记录
     */
    private function saveLoginLog(int $userId, string $requestUri): void
    {
        LogModel::create([
            'action_type'    => ActionType::LOGIN->value,
            'request_uri'    => $requestUri,
            'request_method' => 'POST',
            'ip'             => $this->request->ip(),
            'status'         => 1,
            'create_by'      => $userId,
            'create_time'    => date('Y-m-d H:i:s'),
        ]);
    }

    // ======================== 扫码登录 ========================

    private const QR_DEFAULT_EXPIRE = 300;
    private const QR_MIN_REMAIN = 30;
    private const QR_WAITING = 'WAITING';
    private const QR_SCANNED = 'SCANNED';
    private const QR_CONFIRMED = 'CONFIRMED';
    private const QR_LOGGED_IN = 'LOGGED_IN';
    private const QR_CANCELED = 'CANCELED';

    private static function qrKey(string $ticket): string { return 'auth:qr_code:' . $ticket; }

    private function clientIp(): string
    {
        $xff = $this->request->header('X-Forwarded-For', '');
        if ($xff) return trim(explode(',', $xff)[0]);
        $xrip = $this->request->header('X-Real-IP', '');
        if ($xrip) return trim($xrip);
        return $this->request->ip() ?: 'unknown';
    }

    private function loadCtx(string $ticket): array
    {
        if (empty($ticket)) throw new BusinessException(ResultCode::QR_CODE_NOT_FOUND);
        $raw = RedisClient::get()->get(self::qrKey($ticket));
        if (empty($raw)) throw new BusinessException(ResultCode::QR_CODE_NOT_FOUND);
        return json_decode($raw, true) ?: [];
    }

    private function saveCtx(array $ctx, int $ttl): void
    {
        RedisClient::get()->setex(self::qrKey($ctx['ticket']), $ttl, json_encode($ctx, JSON_UNESCAPED_UNICODE));
    }

    private function remainSeconds(string $ticket): int
    {
        $ttl = RedisClient::get()->ttl(self::qrKey($ticket));
        return ($ttl > 0) ? $ttl : 0;
    }

    private function refreshTtl(string $ticket): int
    {
        $remain = $this->remainSeconds($ticket);
        return $remain < self::QR_MIN_REMAIN ? self::QR_MIN_REMAIN : $remain;
    }

    private static function maskNickname(?string $nickname): ?string
    {
        if (empty($nickname)) return $nickname;
        $chars = mb_str_split($nickname);
        $n = count($chars);
        if ($n <= 1) return $nickname;
        if ($n === 2) return $chars[0] . '*';
        return $chars[0] . str_repeat('*', $n - 2) . $chars[$n - 1];
    }

    private function toStatusVO(array $ctx, int $expireSec): array
    {
        $vo = [
            'ticket'        => $ctx['ticket'],
            'status'        => $ctx['status'],
            'nickname'      => null,
            'avatar'        => null,
            'expireSeconds' => $expireSec,
        ];
        if (in_array($ctx['status'], [self::QR_SCANNED, self::QR_CONFIRMED])) {
            $vo['nickname'] = self::maskNickname($ctx['nickname'] ?? null);
            $vo['avatar'] = $ctx['avatar'] ?? null;
        }
        return $vo;
    }

    public function qrGenerate(): Json
    {
        $ticket = bin2hex(random_bytes(16));
        $ctx = [
            'ticket'  => $ticket,
            'status'  => self::QR_WAITING,
            'userId'  => null,
            'nickname'=> null,
            'avatar'  => null,
            'createdAt'=> null,
            'scannedAt'=> null,
            'confirmedAt'=> null,
            'clientIp' => $this->clientIp(),
        ];
        $this->saveCtx($ctx, self::QR_DEFAULT_EXPIRE);
        return $this->success(['ticket' => $ticket, 'expireSeconds' => self::QR_DEFAULT_EXPIRE]);
    }

    public function qrStatus(): Json
    {
        $ticket = $this->getParam('ticket', '');
        $ctx = $this->loadCtx($ticket);
        return $this->success($this->toStatusVO($ctx, $this->remainSeconds($ticket)));
    }

    public function qrScan(): Json
    {
        $userId = $this->getAuthUserId();
        $ticket = $this->getParam('ticket', '');
        $ctx = $this->loadCtx($ticket);
        if ($ctx['status'] !== self::QR_WAITING) {
            throw new BusinessException(ResultCode::QR_CODE_STATUS_ILLEGAL);
        }
        $user = \app\system\model\User::find($userId);
        if (!$user) throw new BusinessException(ResultCode::ACCOUNT_NOT_FOUND);
        $ctx['userId'] = $userId;
        $ctx['nickname'] = $user->nickname;
        $ctx['avatar'] = $user->avatar;
        $ctx['status'] = self::QR_SCANNED;
        $ctx['scannedAt'] = (int)(microtime(true) * 1000);
        $this->saveCtx($ctx, $this->refreshTtl($ticket));
        return $this->success($this->toStatusVO($ctx, $this->remainSeconds($ticket)));
    }

    public function qrConfirm(): Json
    {
        $userId = $this->getAuthUserId();
        $ticket = $this->getParam('ticket', '');
        $ctx = $this->loadCtx($ticket);
        if ($ctx['status'] !== self::QR_SCANNED) {
            throw new BusinessException(ResultCode::QR_CODE_STATUS_ILLEGAL);
        }
        if (($ctx['userId'] ?? null) != $userId) {
            throw new BusinessException(ResultCode::QR_CODE_USER_MISMATCH);
        }
        $ctx['status'] = self::QR_CONFIRMED;
        $ctx['confirmedAt'] = (int)(microtime(true) * 1000);
        $this->saveCtx($ctx, $this->refreshTtl($ticket));
        return $this->success($this->toStatusVO($ctx, $this->remainSeconds($ticket)));
    }

    public function qrCancel(): Json
    {
        $userId = $this->getAuthUserId();
        $ticket = $this->getParam('ticket', '');
        $ctx = $this->loadCtx($ticket);
        if (!in_array($ctx['status'], [self::QR_WAITING, self::QR_SCANNED, self::QR_CONFIRMED])) {
            throw new BusinessException(ResultCode::QR_CODE_STATUS_ILLEGAL);
        }
        if ($ctx['status'] !== self::QR_WAITING && ($ctx['userId'] ?? null) != $userId) {
            throw new BusinessException(ResultCode::QR_CODE_USER_MISMATCH);
        }
        $ctx['status'] = self::QR_CANCELED;
        $this->saveCtx($ctx, $this->refreshTtl($ticket));
        return $this->success($this->toStatusVO($ctx, $this->remainSeconds($ticket)));
    }

    public function qrLogin(): Json
    {
        $ticket = $this->getParam('ticket', '');
        $ctx = $this->loadCtx($ticket);
        if ($ctx['status'] !== self::QR_CONFIRMED) {
            throw new BusinessException(ResultCode::QR_CODE_STATUS_ILLEGAL);
        }
        $token = $this->service(AuthService::class)->loginByQr((int)($ctx['userId'] ?? 0));
        $ctx['status'] = self::QR_LOGGED_IN;
        $remain = $this->remainSeconds($ticket);
        $this->saveCtx($ctx, $remain > self::QR_MIN_REMAIN ? $remain : self::QR_MIN_REMAIN);
        return $this->success($token);
    }
}
