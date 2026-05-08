<?php

namespace App\Kernel\Base;

use Laravel\Lumen\Routing\Controller;

class BaseController extends Controller
{
    protected function revert(mixed $data = [], string $message = 'success', int $code = 0, int $httpStatus = 200)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $httpStatus, [], JSON_UNESCAPED_UNICODE);
    }
}
