<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function __construct(protected Request $request)
    {
    }

    public function wechatOrder()
    {
        return $this->revert([
            'payment_params' => [],
            'status' => 'mock',
            'message' => 'MVP 暂未接入微信支付，后台确认后可补真实下单参数。',
        ]);
    }
}
