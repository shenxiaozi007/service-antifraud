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
}
