<?php

namespace App\Modules\Service\Business\Auth;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceGuard
{
    public function verify(Request $request): void
    {
        $appId = (string) $request->header('X-Service-App-Id', '');
        $timestamp = (string) $request->header('X-Service-Timestamp', '');
        $sign = (string) $request->header('X-Service-Sign', '');
        $apps = config('service_auth.apps', []);
        $secret = (string) ($apps[$appId] ?? '');

        if ($appId === '' || $timestamp === '' || $sign === '' || $secret === '') {
            throw new HttpException(401, '服务鉴权失败');
        }

        $tolerance = (int) config('service_auth.timestamp_tolerance', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw new HttpException(401, '服务签名已过期');
        }

        $expected = hash_hmac('sha256', $appId.'|'.$timestamp, $secret);
        if (!hash_equals($expected, $sign)) {
            throw new HttpException(401, '服务签名错误');
        }
    }
}
