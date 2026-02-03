<?php

declare(strict_types=1);

namespace app\service;

use app\common\redis\RedisClient;
use app\common\redis\RedisKey;
use app\common\exception\BusinessException;
use app\common\security\AuthenticationToken;
use app\common\security\TokenManagerResolver;
use Gregwar\Captcha\PhraseBuilder;
use think\facade\Cache;

final class AuthService
{
    // ... (rest of the code remains the same)
    public function __construct(
        private readonly UserService $userService = new UserService(),
    ) {
    }

    public function sendSmsLoginCode(string $mobile): void
    {
        $mobile = trim($mobile);
        if ($mobile === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        // TODO: 对接短信服务时替换固定验证码
        $code = '1234';
        $key = RedisKey::format('captcha:sms_login:{}', $mobile);
        RedisClient::get()->setex($key, 300, $code);
    }

    public function loginBySms(string $mobile, string $code): AuthenticationToken
    {
        $mobile = trim($mobile);
        $code = trim($code);

        if ($mobile === '' || $code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        // 校验短信验证码
        $key = RedisKey::format('captcha:sms_login:{}', $mobile);
        $cachedCode = (string) (RedisClient::get()->get($key) ?? '');
        if ($cachedCode === '') {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_EXPIRED);
        }

        if (strcasecmp($cachedCode, $code) !== 0) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        // 通过后立即清理验证码
        RedisClient::get()->del([$key]);

        $user = $this->userService->getUserByMobile($mobile);
        if ($user === null) {
            throw new BusinessException(ResultCode::ACCOUNT_NOT_FOUND);
        }

        if ((int) ($user['status'] ?? 1) !== 1) {
            throw new BusinessException(ResultCode::ACCOUNT_FROZEN);
        }

        // 登录态最小信息
        $authInfo = [
            'userId' => (int) $user['id'],
            'deptId' => $user['dept_id'] ?? null,
            'dataScope' => null,
            'authorities' => [],
        ];

        return (new TokenManagerResolver())->get()->generateToken($authInfo);
    }

    public function loginByWechat(string $code): AuthenticationToken
    {
        $code = trim($code);
        if ($code === '') {
            throw new BusinessException(ResultCode::REQUEST_REQUIRED_PARAMETER_IS_EMPTY);
        }

        // 这里用 code 当作 openid 走本地账号
        $user = $this->userService->getUserByOpenid($code);
        if ($user === null) {
            throw new BusinessException(ResultCode::ACCOUNT_NOT_FOUND);
        }

        if ((int) ($user['status'] ?? 1) !== 1) {
            throw new BusinessException(ResultCode::ACCOUNT_FROZEN);
        }

        $authInfo = [
            'userId' => (int) $user['id'],
            'deptId' => $user['dept_id'] ?? null,
            'dataScope' => null,
            'authorities' => [],
        ];

        return (new TokenManagerResolver())->get()->generateToken($authInfo);
    }

    public function loginByWxMiniAppCode(array $data): AuthenticationToken
    {
        // 小程序 code 复用微信登录流程
        $code = trim((string) ($data['code'] ?? ''));
        return $this->loginByWechat($code);
    }

    public function loginByWxMiniAppPhone(array $data): AuthenticationToken
    {
        // 小程序手机号登录复用微信登录流程
        $code = trim((string) ($data['code'] ?? ''));
        return $this->loginByWechat($code);
    }

    public function getCaptcha(): array
    {
        // 验证码生成与缓存
        $phraseBuilder = new PhraseBuilder(4, '23456789');
        $code = $phraseBuilder->build();
        $width = 160;
        $height = 50;
        $theme = [
            [
                'bgStart' => [240, 246, 255],
                'bgEnd' => [228, 237, 252],
                'text' => [[82, 118, 237], [98, 140, 244], [62, 98, 220]],
                'line' => [198, 214, 248],
            ],
            [
                'bgStart' => [240, 252, 248],
                'bgEnd' => [226, 244, 236],
                'text' => [[70, 171, 136], [86, 190, 152], [54, 152, 118]],
                'line' => [195, 235, 222],
            ],
            [
                'bgStart' => [252, 245, 247],
                'bgEnd' => [246, 233, 238],
                'text' => [[225, 120, 145], [238, 146, 167], [203, 102, 126]],
                'line' => [245, 210, 219],
            ],
        ];
        $palette = $theme[array_rand($theme)];

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // 背景渐变
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max(1, $height - 1);
            $r = (int) ($palette['bgStart'][0] * (1 - $ratio) + $palette['bgEnd'][0] * $ratio);
            $g = (int) ($palette['bgStart'][1] * (1 - $ratio) + $palette['bgEnd'][1] * $ratio);
            $b = (int) ($palette['bgStart'][2] * (1 - $ratio) + $palette['bgEnd'][2] * $ratio);
            $rowColor = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $rowColor);
        }

        // 干扰线
        $lineColor = imagecolorallocate($image, $palette['line'][0], $palette['line'][1], $palette['line'][2]);
        $lineCount = random_int(1, 2);
        $lineStartMax = (int) floor($width / 3);
        $lineEndMin = (int) floor($width * 2 / 3);
        for ($i = 0; $i < $lineCount; $i++) {
            imageline(
                $image,
                random_int(0, $lineStartMax),
                random_int(5, $height - 5),
                random_int($lineEndMin, $width),
                random_int(5, $height - 5),
                $lineColor
            );
        }

        // 噪点
        for ($i = 0; $i < 45; $i++) {
            $dot = $palette['text'][array_rand($palette['text'])];
            $dotColor = imagecolorallocate($image, $dot[0], $dot[1], $dot[2]);
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $dotColor);
        }

        $fontDir = dirname(__DIR__, 2) . '/vendor/gregwar/captcha/src/Gregwar/Captcha/Font';
        $fonts = is_dir($fontDir) ? glob($fontDir . '/*.ttf') : [];
        $font = $fonts ? $fonts[array_rand($fonts)] : null;

        $chars = preg_split('//u', $code, -1, PREG_SPLIT_NO_EMPTY);
        $step = ($width - 20) / max(1, count($chars));
        $x = 10;

        foreach ($chars as $char) {
            $text = $palette['text'][array_rand($palette['text'])];
            $textColor = imagecolorallocate($image, $text[0], $text[1], $text[2]);
            $shadow = imagecolorallocate($image, 255, 255, 255);
            $angle = random_int(-12, 12);
            $fontSize = random_int(22, 26);
            $y = random_int(32, 40);

            if ($font) {
                imagettftext($image, $fontSize, $angle, (int) $x + 1, $y + 1, $shadow, $font, $char);
                imagettftext($image, $fontSize, $angle, (int) $x, $y, $textColor, $font, $char);
            } else {
                // 没有字体文件时用系统字体兜底
                imagestring($image, 5, (int) $x, 15, $char, $textColor);
            }

            $x += $step;
        }
        $captchaId = bin2hex(random_bytes(16));

        $key = RedisKey::format('captcha:image:{}', $captchaId);
        // Redis 优先，Cache 兜底
        Cache::set($key, $code, 300);
        try {
            RedisClient::get()->setex($key, 300, $code);
        } catch (\Throwable) {
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        // 前端直接展示 base64 图片
        $captchaBase64 = 'data:image/png;base64,' . base64_encode((string) $imageData);

        return [
            'captchaId' => $captchaId,
            'captchaBase64' => $captchaBase64,
        ];
    }

    public function login(string $username, string $password, string $captchaId, string $captchaCode): AuthenticationToken
    {
        if ($captchaId === '' || $captchaCode === '') {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        // 先读 Redis，不存在再回退 Cache
        $captchaKey = RedisKey::format('captcha:image:{}', $captchaId);
        $cachedCode = '';
        try {
            $cachedCode = (string) (RedisClient::get()->get($captchaKey) ?? '');
        } catch (\Throwable) {
        }
        if ($cachedCode === '') {
            $cachedCode = (string) Cache::get($captchaKey, '');
        }
        if ($cachedCode === '') {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_EXPIRED);
        }

        if (strcasecmp(trim($cachedCode), trim($captchaCode)) !== 0) {
            throw new BusinessException(ResultCode::USER_VERIFICATION_CODE_ERROR);
        }

        // 校验用户名/密码与账号状态
        $user = $this->userService->getUserByUsername($username);

        if ($user === null) {
            throw new BusinessException(ResultCode::ACCOUNT_NOT_FOUND);
        }

        if ((int) ($user['status'] ?? 1) !== 1) {
            throw new BusinessException(ResultCode::ACCOUNT_FROZEN);
        }

        $hash = (string) ($user['password'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            throw new BusinessException(ResultCode::USER_PASSWORD_ERROR);
        }

        $authInfo = [
            'userId' => (int) $user['id'],
            'deptId' => $user['dept_id'] ?? null,
            'dataScope' => null,
            'authorities' => [],
        ];

        return (new TokenManagerResolver())->get()->generateToken($authInfo);
    }

    public function refreshToken(string $refreshToken): AuthenticationToken
    {
        if ($refreshToken === '') {
            throw new BusinessException(ResultCode::REFRESH_TOKEN_INVALID);
        }

        return (new TokenManagerResolver())->get()->refreshToken($refreshToken);
    }

    public function logout(?string $accessToken, ?string $refreshToken): void
    {
        (new TokenManagerResolver())->get()->invalidate($accessToken, $refreshToken);
    }
}
