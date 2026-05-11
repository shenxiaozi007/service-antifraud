<?php

namespace App\Exceptions\Common;

use App\Exceptions\BaseException;

class AppException extends BaseException
{
    protected static array $codeMaps = [
        100000 => ['message' => 'something wrong.'],
        100001 => ['message' => 'resource not found.'],
        100003 => ['message' => '参数错误.'],
        100007 => ['message' => 'method not allowed.'],
        110006 => ['message' => 'data not found'],
    ];
}
