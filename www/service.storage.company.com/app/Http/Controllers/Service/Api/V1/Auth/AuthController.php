<?php

namespace App\Http\Controllers\Service\Api\V1\Auth;

use App\Kernel\Base\BaseController;
use App\Modules\Service\Business\Auth\AuthBusiness;
use App\Modules\Service\Business\Auth\ServiceGuard;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    /**
     * 发送邮箱或手机号验证码。
     *
     * @param Request $request 请求参数，包含 account/scene
     * @param AuthBusiness $business 认证业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendCode(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->sendCode($request->all()));
    }

    /**
     * 使用验证码登录或注册。
     *
     * @param Request $request 请求参数，包含 account/code/scene
     * @param AuthBusiness $business 认证业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function codeLogin(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->codeLogin($request->all()));
    }

    /**
     * 使用密码注册邮箱或手机号账号。
     *
     * @param Request $request 请求参数，包含 account/password/password_confirmation/nickname
     * @param AuthBusiness $business 认证业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordRegister(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->passwordRegister($request->all()));
    }

    /**
     * 使用邮箱或手机号密码登录。
     *
     * @param Request $request 请求参数，包含 account/password
     * @param AuthBusiness $business 认证业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordLogin(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->passwordLogin($request->all()));
    }

    /**
     * 使用微信身份登录或注册。
     *
     * @param Request $request 请求参数，包含 code/openid 等微信登录信息
     * @param AuthBusiness $business 认证业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function wechatLogin(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->wechatLogin($request->all()));
    }

    /**
     * 校验公共登录 Token 并返回用户信息。
     *
     * @param Request $request 请求参数或 Authorization 头中的 token
     * @param AuthBusiness $business 认证业务服务
     * @param ServiceGuard $serviceGuard 服务间调用签名校验
     * @return \Illuminate\Http\JsonResponse
     */
    public function introspect(Request $request, AuthBusiness $business, ServiceGuard $serviceGuard)
    {
        $serviceGuard->verify($request);

        return $this->revert($business->introspect($this->bearerToken($request)));
    }

    protected function bearerToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return (string) $request->input('token', '');
    }
}
