<?php

namespace App\Http\Controllers\Service\Api\V1\Auth;

use App\Kernel\Base\BaseController;
use App\Modules\Service\Business\Auth\AuthBusiness;
use App\Modules\Service\Business\Auth\ServiceGuard;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function sendCode(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->sendCode($request->all()));
    }

    public function codeLogin(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->codeLogin($request->all()));
    }

    public function wechatLogin(Request $request, AuthBusiness $business)
    {
        return $this->revert($business->wechatLogin($request->all()));
    }

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
