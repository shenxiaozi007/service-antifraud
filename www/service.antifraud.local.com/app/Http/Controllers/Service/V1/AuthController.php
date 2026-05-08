<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;
use App\Modules\Service\AuthBusiness;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(protected Request $request, protected AuthBusiness $business)
    {
    }

    public function wechatLogin()
    {
        return $this->revert($this->business->wechatLogin($this->request->all()));
    }
}
