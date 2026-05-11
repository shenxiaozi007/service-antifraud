<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;

class SystemController extends BaseController
{
    public function health()
    {
        return $this->revert([
            'service' => env('APP_NAME', 'guardian-max'),
            'environment' => env('APP_ENV', 'production'),
            'status' => 'ok',
            'time' => date('c'),
        ]);
    }
}
