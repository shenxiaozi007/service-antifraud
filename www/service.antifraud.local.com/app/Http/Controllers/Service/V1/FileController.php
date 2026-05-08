<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;
use App\Modules\Service\FileBusiness;
use Illuminate\Http\Request;

class FileController extends BaseController
{
    public function __construct(protected Request $request, protected FileBusiness $business)
    {
    }

    public function uploadToken()
    {
        return $this->revert($this->business->uploadToken($this->request));
    }
}
