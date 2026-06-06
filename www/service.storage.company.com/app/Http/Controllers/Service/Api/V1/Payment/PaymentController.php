<?php

namespace App\Http\Controllers\Service\Api\V1\Payment;

use App\Kernel\Base\BaseController;
use App\Modules\Service\Business\Auth\TokenGuard;
use App\Modules\Service\Business\Payment\PaymentBusiness;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function packages(Request $request, PaymentBusiness $business)
    {
        return $this->revert($business->packages((string) $request->get('project_code', 'antifraud')));
    }

    public function wechatJsapiOrder(Request $request, TokenGuard $guard, PaymentBusiness $business)
    {
        $user = $guard->user($request);

        return $this->revert($business->wechatJsapiOrder($user['id'], $request->all()));
    }

    public function wechatNotify(Request $request, PaymentBusiness $business)
    {
        $business->wechatNotify($request->all(), $request->headers->all(), $request->getContent());

        return response()->json(['code' => 'SUCCESS', 'message' => '成功']);
    }
}
