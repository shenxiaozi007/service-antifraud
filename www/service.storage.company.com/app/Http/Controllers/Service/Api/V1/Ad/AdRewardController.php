<?php

namespace App\Http\Controllers\Service\Api\V1\Ad;

use App\Kernel\Base\BaseController;
use App\Modules\Service\Business\Ad\AdRewardBusiness;
use App\Modules\Service\Business\Auth\ServiceGuard;
use Illuminate\Http\Request;

class AdRewardController extends BaseController
{
    /**
     * 服务端发放广告奖励积分。
     *
     * @param Request $request HTTP 请求
     * @param AdRewardBusiness $business 广告奖励业务
     * @param ServiceGuard $serviceGuard 服务鉴权
     * @return mixed
     */
    public function reward(Request $request, AdRewardBusiness $business, ServiceGuard $serviceGuard)
    {
        $serviceGuard->verify($request);

        return $this->revert($business->reward($request->all()));
    }
}
