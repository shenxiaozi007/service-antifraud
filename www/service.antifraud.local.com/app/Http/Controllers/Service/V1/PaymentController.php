<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;
use App\Libraries\CommonService\CommonServiceClient;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function __construct(protected Request $request, protected CommonServiceClient $commonServiceClient)
    {
    }

    public function packages()
    {
        return $this->revert($this->commonServiceClient->paymentPackages($this->request->all()));
    }

    public function wechatOrder()
    {
        return $this->revert($this->commonServiceClient->wechatOrder($this->bearerToken(), $this->request->all()));
    }

    /**
     * 创建支付宝扫码支付订单。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function alipayOrder()
    {
        return $this->revert($this->commonServiceClient->alipayOrder($this->bearerToken(), $this->request->all()));
    }

    /**
     * 查询当前用户的支付订单状态。
     *
     * @param string $orderNo 支付订单号
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderStatus(string $orderNo)
    {
        return $this->revert($this->commonServiceClient->paymentOrder($this->bearerToken(), $orderNo));
    }

    protected function bearerToken(): string
    {
        $header = (string) $this->request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return (string) $this->request->input('token', '');
    }
}
