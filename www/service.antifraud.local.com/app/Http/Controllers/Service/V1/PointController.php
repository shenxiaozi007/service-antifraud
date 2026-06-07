<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;
use App\Modules\Service\PointBusiness;
use Illuminate\Http\Request;

class PointController extends BaseController
{
    public function __construct(protected Request $request, protected PointBusiness $business)
    {
    }

    public function transactions()
    {
        return $this->revert($this->business->transactions($this->request));
    }

    /**
     * 领取观看广告积分奖励。
     *
     * @return mixed
     */
    public function adReward()
    {
        return $this->revert($this->business->adReward($this->request));
    }

    /**
     * 每日签到领取积分。
     *
     * @return mixed
     */
    public function checkIn()
    {
        return $this->revert($this->business->checkIn($this->request));
    }
}
