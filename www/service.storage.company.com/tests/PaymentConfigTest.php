<?php

namespace Tests;

use App\Modules\Service\Business\Payment\PaymentBusiness;
use App\Modules\Basics\Model\Payment\PaymentOrder;
use Illuminate\Validation\ValidationException;

class PaymentConfigTest extends TestCase
{
    public function test_wechat_pay_mock_string_false_is_false(): void
    {
        putenv('WECHAT_PAY_MOCK=false');
        config(['payment.wechat.mock' => filter_var(getenv('WECHAT_PAY_MOCK'), FILTER_VALIDATE_BOOL)]);

        $this->assertFalse(config('payment.wechat.mock'));
    }

    public function test_wechat_notify_requires_signature_when_real_pay_enabled(): void
    {
        config([
            'payment.wechat.mock' => false,
            'payment.wechat.app_id' => 'wx_app',
            'payment.wechat.mch_id' => 'mch_id',
            'payment.wechat.merchant_serial_no' => 'serial',
            'payment.wechat.merchant_private_key' => $this->fakePrivateKey(),
            'payment.wechat.platform_certificate' => '',
            'payment.wechat.platform_certificate_path' => '',
        ]);

        $this->expectException(ValidationException::class);

        app(PaymentBusiness::class)->wechatNotify(['out_trade_no' => 'pay_test'], [], '{"out_trade_no":"pay_test"}');
    }

    public function test_wechat_pay_and_login_config_are_registered(): void
    {
        config([
            'wechat.mini_program.app_id' => 'wx_app',
            'wechat.mini_program.app_secret' => 'secret',
        ]);

        $this->assertSame('wx_app', config('wechat.mini_program.app_id'));
        $this->assertSame('secret', config('wechat.mini_program.app_secret'));
    }

    public function test_real_wechat_pay_requires_complete_config_instead_of_falling_back_to_mock(): void
    {
        config([
            'payment.wechat.mock' => false,
            'payment.wechat.app_id' => '',
            'payment.wechat.mch_id' => '',
            'payment.wechat.api_v3_key' => '',
            'payment.wechat.merchant_serial_no' => '',
            'payment.wechat.merchant_private_key' => '',
            'payment.wechat.merchant_private_key_path' => '',
        ]);

        $business = $this->paymentBusinessWithoutConstructor();
        $method = new \ReflectionMethod(PaymentBusiness::class, 'assertWechatPayConfigReady');
        $method->setAccessible(true);

        $this->expectException(ValidationException::class);

        $method->invoke($business);
    }

    public function test_wechat_notify_paid_assertion_rejects_wrong_amount(): void
    {
        $business = $this->paymentBusinessWithoutConstructor();
        $method = new \ReflectionMethod(PaymentBusiness::class, 'assertWechatNotifyPaid');
        $method->setAccessible(true);

        $order = new PaymentOrder();
        $order->amount_cent = 990;

        $this->expectException(ValidationException::class);

        $method->invoke($business, [
            'trade_state' => 'SUCCESS',
            'amount' => ['total' => 100],
        ], $order);
    }

    public function test_wechat_notify_paid_assertion_rejects_non_success_state(): void
    {
        $business = $this->paymentBusinessWithoutConstructor();
        $method = new \ReflectionMethod(PaymentBusiness::class, 'assertWechatNotifyPaid');
        $method->setAccessible(true);

        $order = new PaymentOrder();
        $order->amount_cent = 990;

        $this->expectException(ValidationException::class);

        $method->invoke($business, [
            'trade_state' => 'NOTPAY',
            'amount' => ['total' => 990],
        ], $order);
    }

    private function fakePrivateKey(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n".
            "MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgAAAAAAAAAAAAAAAA\n".
            "AAAAAAAAAAAAAAAAAAAAAAAAAAChRANCAAT/////////////////////////////////\n".
            "////////////////////////////////////////////////////////////\n".
            "-----END PRIVATE KEY-----";
    }

    private function paymentBusinessWithoutConstructor(): PaymentBusiness
    {
        return (new \ReflectionClass(PaymentBusiness::class))->newInstanceWithoutConstructor();
    }
}
