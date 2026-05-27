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
}
