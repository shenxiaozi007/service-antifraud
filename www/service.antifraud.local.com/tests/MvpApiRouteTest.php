<?php

namespace Tests;

class MvpApiRouteTest extends TestCase
{
    public function test_antifraud_service_mvp_routes_are_registered(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            'GET/api/v1/me',
            'POST/api/v1/auth/password-register',
            'POST/api/v1/auth/password-login',
            'POST/api/v1/files/register',
            'POST/api/v1/analysis/image',
            'POST/api/v1/analysis/audio',
            'GET/api/v1/analysis/{recordId}',
            'GET/api/v1/analysis-records',
            'DELETE/api/v1/analysis/{recordId}',
            'GET/api/v1/points/transactions',
            'GET/api/v1/payments/packages',
            'POST/api/v1/payments/wechat/order',
            'POST/api/v1/payments/alipay/order',
            'GET/api/v1/payments/orders/{orderNo}',
            'GET/management/proxy/users',
            'GET/management/proxy/analysis-records',
            'GET/management/proxy/analysis-records/{recordId}',
            'GET/management/proxy/file-assets',
            'GET/management/proxy/point-transactions',
            'GET/management/proxy/risk-rules',
            'POST/management/proxy/risk-rules',
            'PUT/management/proxy/risk-rules/{ruleId}',
            'POST/management/proxy/analysis/{recordId}/retry',
            'POST/management/proxy/analysis-records/{recordId}/retry',
        ] as $route) {
            $this->assertArrayHasKey($route, $routes, $route.' should be registered');
        }
    }
}
