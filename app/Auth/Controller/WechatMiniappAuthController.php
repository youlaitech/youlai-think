<?php declare(strict_types=1);

namespace app\Auth\Controller;

use app\controller\BaseController;
use app\Auth\Service\WechatMiniappAuthService;
use OpenApi\Annotations as OA;
use think\response\Json;

/**
 * @OA\Tag(name="13.微信小程序认证")
 */
final class WechatMiniappAuthController extends BaseController
{
    /**
     * 静默登录
     *
     * @OA\Post(
     *     path="/api/v1/wechat/miniapp/auth/silent-login",
     *     summary="静默登录",
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

        $result = $this->service(WechatMiniappAuthService::class)->silentLogin($code);

        return $this->success($result);
    }

    /**
     * 手机号快捷登录
     *
     * @OA\Post(
     *     path="/api/v1/wechat/miniapp/auth/phone-login",
     *     summary="手机号快捷登录",
     *     @OA\Parameter(name="loginCode", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="phoneCode", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function phoneLogin(): Json
    {
        $loginCode = $this->getParam('loginCode', '');
        $phoneCode = $this->getParam('phoneCode', '');

        if (empty($loginCode) || empty($phoneCode)) {
            return $this->fail('A0400', 'loginCode和phoneCode不能为空');
        }

        $result = $this->service(WechatMiniappAuthService::class)->phoneLogin($loginCode, $phoneCode);

        return $this->success($result, '登录成功');
    }

    /**
     * 绑定手机号
     *
     * @OA\Post(
     *     path="/api/v1/wechat/miniapp/auth/bind-mobile",
     *     summary="绑定手机号",
     *     @OA\Parameter(name="openId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="mobile", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="smsCode", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response="200", description="成功")
     * )
     */
    public function bindMobile(): Json
    {
        $openId = $this->getParam('openId', '');
        $mobile = $this->getParam('mobile', '');
        $smsCode = $this->getParam('smsCode', '');

        if (empty($openId) || empty($mobile) || empty($smsCode)) {
            return $this->fail('A0400', 'openId、mobile和smsCode不能为空');
        }

        $result = $this->service(WechatMiniappAuthService::class)->bindMobile($openId, $mobile, $smsCode);

        return $this->success($result, '绑定成功');
    }
}
