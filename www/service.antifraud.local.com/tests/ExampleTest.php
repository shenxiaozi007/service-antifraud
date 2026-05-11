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
    public function test_that_base_endpoint_returns_a_successful_response()
    {
        $this->get('/');

        $this->assertEquals(
            $this->app->version(), $this->response->getContent()
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
