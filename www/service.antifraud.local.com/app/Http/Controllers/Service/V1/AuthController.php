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

    public function sendCode()
    {
        return $this->revert($this->business->sendCode($this->request->all()));
    }

    public function codeLogin()
    {
        return $this->revert($this->business->codeLogin($this->request->all()));
    }

    /**
     * 使用邮箱密码注册。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordRegister()
    {
        return $this->revert($this->business->passwordRegister($this->request->all()));
    }

    /**
     * 使用邮箱密码登录。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordLogin()
    {
        return $this->revert($this->business->passwordLogin($this->request->all()));
    }
}
