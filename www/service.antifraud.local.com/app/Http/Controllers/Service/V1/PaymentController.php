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

    protected function bearerToken(): string
    {
        $header = (string) $this->request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return (string) $this->request->input('token', '');
    }
}
