<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;
use App\Modules\Service\UserBusiness;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    public function __construct(protected Request $request, protected UserBusiness $business)
    {
    }

    public function me()
    {
        return $this->revert($this->business->me($this->request));
    }
}
