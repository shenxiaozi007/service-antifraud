<?php

namespace App\Http\Controllers\Service\Api\V1\Payment;

use App\Kernel\Base\BaseController;
use App\Modules\Service\Business\Auth\TokenGuard;
use App\Modules\Service\Business\Payment\PaymentBusiness;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    /**
     * 获取指定项目的充值套餐。
     *
     * @param Request $request 请求参数，包含 project_code
     * @param PaymentBusiness $business 支付业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function packages(Request $request, PaymentBusiness $business)
    {
        return $this->revert($business->packages((string) $request->get('project_code', 'antifraud')));
    }

    /**
     * 创建微信 JSAPI 支付订单。
     *
     * @param Request $request 请求参数，包含 project_code/package_id/openid
     * @param TokenGuard $guard 用户登录态校验
     * @param PaymentBusiness $business 支付业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function wechatJsapiOrder(Request $request, TokenGuard $guard, PaymentBusiness $business)
    {
        $user = $guard->user($request);

        return $this->revert($business->wechatJsapiOrder($user['id'], $request->all()));
    }

    /**
     * 创建支付宝扫码支付订单。
     *
     * @param Request $request 请求参数，包含 project_code/package_id
     * @param TokenGuard $guard 用户登录态校验
     * @param PaymentBusiness $business 支付业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function alipayPrecreateOrder(Request $request, TokenGuard $guard, PaymentBusiness $business)
    {
        $user = $guard->user($request);

        return $this->revert($business->alipayPrecreateOrder($user['id'], $request->all()));
    }

    /**
     * 查询当前用户的支付订单状态。
     *
     * @param string $orderNo 支付订单号
     * @param Request $request 请求参数或 Authorization 头中的 token
     * @param TokenGuard $guard 用户登录态校验
     * @param PaymentBusiness $business 支付业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderStatus(string $orderNo, Request $request, TokenGuard $guard, PaymentBusiness $business)
    {
        $user = $guard->user($request);

        return $this->revert($business->orderStatus($user['id'], $orderNo));
    }

    /**
     * 处理微信支付异步通知。
     *
     * @param Request $request 微信支付回调请求
     * @param PaymentBusiness $business 支付业务服务
     * @return \Illuminate\Http\JsonResponse
     */
    public function wechatNotify(Request $request, PaymentBusiness $business)
    {
        $business->wechatNotify($request->all(), $request->headers->all(), $request->getContent());

        return response()->json(['code' => 'SUCCESS', 'message' => '成功']);
    }

    /**
     * 处理支付宝支付异步通知。
     *
     * @param Request $request 支付宝回调请求
     * @param PaymentBusiness $business 支付业务服务
     * @return \Illuminate\Http\Response
     */
    public function alipayNotify(Request $request, PaymentBusiness $business)
    {
        $business->alipayNotify($request->all());

        return response('success');
    }
}
