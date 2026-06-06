<?php

namespace Tests;

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_that_base_endpoint_returns_homepage()
    {
        $this->get('/');

        $this->assertResponseOk();
        $this->assertStringContainsString(
            '<title>HXC | 工程系统与个人品牌</title>',
            file_get_contents(base_path('public/home.html'))
        );
    }

    public function test_that_health_endpoint_returns_ok()
    {
        $this->get('/api/v1/system/health');

        $this->seeJson([
            'code' => 0,
            'message' => 'success',
            'status' => 'ok',
        ]);
    }

    public function test_payment_packages_route_is_registered()
    {
        $routes = app('router')->getRoutes();
        $this->assertArrayHasKey('GET/api/v1/payments/packages', $routes);
    }

    public function test_file_register_route_is_registered()
    {
        $routes = app('router')->getRoutes();
        $this->assertArrayHasKey('POST/api/v1/files/register', $routes);
    }

    public function test_management_retry_plan_route_is_registered()
    {
        $routes = app('router')->getRoutes();
        $this->assertArrayHasKey('POST/management/proxy/analysis/{recordId}/retry', $routes);
    }
}
