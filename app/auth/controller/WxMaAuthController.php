<?php declare(strict_types=1);

namespace app\auth\controller;

use app\controller\BaseController;
use app\auth\service\WxMaAuthService;
use OpenApi\Annotations as OA;
use think\response\Json;

/**
 * @OA\Tag(name="13.微信小程序认证")
 */
final class WxMaAuthController extends BaseController
{
    /**
     * 静默登录
     *
     * @OA\Post(
     *     path="/api/v1/wxma/auth/silent-login",
     *     summary="静默登录",
     *     tags={"13.微信小程序认证"},
     *     @OA\Parameter(name="code", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function silentLogin(): Json
    {
        $code = $this->getParam('code', '');

        if (empty($code)) {
            return $this->fail('A0400', 'code不能为空');
        }

        $result = $this->service(WxMaAuthService::class)->silentLogin($code);

        return $this->success($result);
    }

    /**
     * 手机号快捷登录
     *
     * @OA\Post(
     *     path="/api/v1/wxma/auth/phone-login",
     *     summary="手机号快捷登录",
     *     tags={"13.微信小程序认证"},
     *     @OA\Parameter(name="loginCode", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="phoneCode", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function phoneLogin(): Json
    {
        $loginCode = $this->getParam('login_code', '');
        $phoneCode = $this->getParam('phone_code', '');

        if (empty($loginCode) || empty($phoneCode)) {
            return $this->fail('A0400', 'loginCode和phoneCode不能为空');
        }

        $result = $this->service(WxMaAuthService::class)->phoneLogin($loginCode, $phoneCode);

        return $this->success($result, '登录成功');
    }

    /**
     * 绑定手机号
     *
     * @OA\Post(
     *     path="/api/v1/wxma/auth/bind-mobile",
     *     summary="绑定手机号",
     *     tags={"13.微信小程序认证"},
     *     @OA\Parameter(name="openId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="mobile", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="smsCode", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function bindMobile(): Json
    {
        $openId = $this->getParam('open_id', '');
        $mobile = $this->getParam('mobile', '');
        $smsCode = $this->getParam('sms_code', '');

        if (empty($openId) || empty($mobile) || empty($smsCode)) {
            return $this->fail('A0400', 'openId、mobile和smsCode不能为空');
        }

        $result = $this->service(WxMaAuthService::class)->bindMobile($openId, $mobile, $smsCode);

        return $this->success($result, '绑定成功');
    }
}
