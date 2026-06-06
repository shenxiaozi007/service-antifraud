<?php

namespace Tests;

use App\Modules\Service\Business\Payment\PaymentBusiness;

class ExampleTest extends TestCase
{
    public function test_file_disks_endpoint_returns_supported_disks(): void
    {
        $this->get('/service/api/v1/file/disks');

        $this->seeStatusCode(200);
        $this->seeJsonContains([
            'code' => 0,
            'module' => 'object-storage-service',
        ]);
    }

    public function test_payment_packages_route_is_registered(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertArrayHasKey('GET/service/api/v1/payment/packages', $routes);
    }

    public function test_wechat_notify_returns_official_success_shape_in_mock_mode(): void
    {
        $mock = \Mockery::mock(PaymentBusiness::class);
        $mock->shouldReceive('wechatNotify')->once()->andReturn([
            'success' => true,
            'order_no' => 'pay_test',
            'status' => 'paid',
        ]);
        $this->app->instance(PaymentBusiness::class, $mock);

        $this->post('/service/api/v1/payment/wechat/notify', ['out_trade_no' => 'pay_test']);

        $this->seeStatusCode(200);
        $this->seeJsonEquals(['code' => 'SUCCESS', 'message' => '成功']);
    }
}
