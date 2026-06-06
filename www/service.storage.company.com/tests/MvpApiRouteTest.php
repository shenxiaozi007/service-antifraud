<?php

namespace Tests;

class MvpApiRouteTest extends TestCase
{
    public function test_public_common_service_mvp_routes_are_registered(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            'POST/service/api/v1/auth/wechat-login',
            'POST/service/api/v1/auth/send-code',
            'POST/service/api/v1/auth/code-login',
            'POST/service/api/v1/auth/introspect',
            'POST/service/api/v1/file/upload',
            'GET/service/api/v1/file/detail',
            'GET/service/api/v1/file/download-url',
            'GET/service/api/v1/wallet/balance',
            'GET/service/api/v1/wallet/transactions',
            'GET/service/api/v1/wallet/transactions-by-user',
            'POST/service/api/v1/wallet/freeze',
            'POST/service/api/v1/wallet/confirm',
            'POST/service/api/v1/wallet/release',
            'GET/service/api/v1/payment/packages',
            'POST/service/api/v1/payment/wechat/jsapi-order',
            'POST/service/api/v1/payment/wechat/notify',
        ] as $route) {
            $this->assertArrayHasKey($route, $routes, $route.' should be registered');
        }
    }
}
