<?php

namespace App\Http\Controllers\Service\Api\V1\Wallet;

use App\Kernel\Base\BaseController;
use App\Modules\Service\Business\Auth\ServiceGuard;
use App\Modules\Service\Business\Auth\TokenGuard;
use App\Modules\Service\Business\Wallet\WalletBusiness;
use Illuminate\Http\Request;

class WalletController extends BaseController
{
    public function balance(Request $request, TokenGuard $guard, WalletBusiness $business)
    {
        $user = $guard->user($request);

        return $this->revert($business->balance($user['id'], (string) $request->get('project_code', 'antifraud')));
    }

    public function transactions(Request $request, TokenGuard $guard, WalletBusiness $business)
    {
        $user = $guard->user($request);

        return $this->revert($business->transactions(
            $user['id'],
            (string) $request->get('project_code', 'antifraud'),
            (int) $request->get('page_size', 20)
        ));
    }

    public function transactionsByUser(Request $request, WalletBusiness $business, ServiceGuard $serviceGuard)
    {
        $serviceGuard->verify($request);

        return $this->revert($business->transactionsByUser($request->all()));
    }

    public function freeze(Request $request, WalletBusiness $business, ServiceGuard $serviceGuard)
    {
        $serviceGuard->verify($request);

        return $this->revert($business->freeze($request->all()));
    }

    public function confirm(Request $request, WalletBusiness $business, ServiceGuard $serviceGuard)
    {
        $serviceGuard->verify($request);

        return $this->revert($business->confirm($request->all()));
    }

    public function release(Request $request, WalletBusiness $business, ServiceGuard $serviceGuard)
    {
        $serviceGuard->verify($request);

        return $this->revert($business->release($request->all()));
    }
}
