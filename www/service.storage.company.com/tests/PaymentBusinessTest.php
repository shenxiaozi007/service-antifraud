<?php

namespace Tests;

use App\Modules\Basics\Dao\Auth\AuthIdentityDao;
use App\Modules\Basics\Dao\Payment\PaymentOrderDao;
use App\Modules\Basics\Dao\Payment\PaymentPackageDao;
use App\Modules\Basics\Model\Payment\PaymentPackage;
use App\Modules\Basics\Model\Payment\PaymentOrder;
use App\Modules\Service\Business\Payment\PaymentBusiness;
use App\Modules\Service\Business\Wallet\WalletBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentBusinessTest extends TestCase
{
    public function test_wechat_notify_marks_order_paid_and_recharges_wallet_once(): void
    {
        config(['payment.wechat.mock' => true]);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $order = new InMemoryPaymentOrder('pay_mvp_1', 'pending');
        $wallet = new InMemoryPaymentWallet();
        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDao(),
            new InMemoryPaymentOrderDao($order),
            $wallet,
            new InMemoryPaymentIdentityDao()
        );

        $result = $business->wechatNotify([
            'out_trade_no' => 'pay_mvp_1',
            'transaction_id' => 'wx_txn_1',
            'trade_state' => 'SUCCESS',
            'amount' => ['total' => 990, 'payer_total' => 990],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('paid', $order->status);
        $this->assertSame('wx_txn_1', $order->transaction_id);
        $this->assertSame(1, $wallet->rechargeCount);
        $this->assertSame([
            'user_id' => 10001,
            'project_code' => 'antifraud',
            'amount' => 100,
            'related_no' => 'pay_mvp_1',
            'remark' => '微信支付充值',
        ], $wallet->lastRecharge);
    }

    public function test_wechat_notify_is_idempotent_for_paid_order(): void
    {
        config(['payment.wechat.mock' => true]);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $order = new InMemoryPaymentOrder('pay_mvp_2', 'paid');
        $wallet = new InMemoryPaymentWallet();
        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDao(),
            new InMemoryPaymentOrderDao($order),
            $wallet,
            new InMemoryPaymentIdentityDao()
        );

        $result = $business->wechatNotify([
            'out_trade_no' => 'pay_mvp_2',
            'trade_state' => 'SUCCESS',
            'amount' => ['total' => 990],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('paid', $result['status']);
        $this->assertSame(0, $wallet->rechargeCount);
    }

    public function test_wechat_notify_rejects_non_pending_non_paid_order(): void
    {
        config(['payment.wechat.mock' => true]);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDao(),
            new InMemoryPaymentOrderDao(new InMemoryPaymentOrder('pay_mvp_3', 'closed')),
            new InMemoryPaymentWallet(),
            new InMemoryPaymentIdentityDao()
        );

        $this->expectException(ValidationException::class);

        $business->wechatNotify([
            'out_trade_no' => 'pay_mvp_3',
            'trade_state' => 'SUCCESS',
            'amount' => ['total' => 990],
        ]);
    }

    public function test_wechat_notify_rejects_amount_mismatch_without_recharge(): void
    {
        config(['payment.wechat.mock' => true]);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $wallet = new InMemoryPaymentWallet();
        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDao(),
            new InMemoryPaymentOrderDao(new InMemoryPaymentOrder('pay_mvp_amount', 'pending')),
            $wallet,
            new InMemoryPaymentIdentityDao()
        );

        $this->expectException(ValidationException::class);

        try {
            $business->wechatNotify([
                'out_trade_no' => 'pay_mvp_amount',
                'trade_state' => 'SUCCESS',
                'amount' => ['total' => 1, 'payer_total' => 1],
            ]);
        } finally {
            $this->assertSame(0, $wallet->rechargeCount);
        }
    }

    public function test_wechat_notify_requires_signature_when_mock_disabled(): void
    {
        config(['payment.wechat.mock' => false]);

        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDao(),
            new InMemoryPaymentOrderDao(new InMemoryPaymentOrder('pay_mvp_signature', 'pending')),
            new InMemoryPaymentWallet(),
            new InMemoryPaymentIdentityDao()
        );

        $this->expectException(ValidationException::class);

        $business->wechatNotify([
            'out_trade_no' => 'pay_mvp_signature',
            'trade_state' => 'SUCCESS',
            'amount' => ['total' => 990],
        ], [], '{"out_trade_no":"pay_mvp_signature"}');
    }

    public function test_alipay_precreate_order_returns_mock_qr_code(): void
    {
        config(['payment.alipay.mock' => true]);

        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDaoWithPackage(),
            new InMemoryPaymentOrderStoreDao(),
            new InMemoryPaymentWallet(),
            new InMemoryPaymentIdentityDao()
        );

        $result = $business->alipayPrecreateOrder(10001, [
            'project_code' => 'antifraud',
            'package_id' => 1,
        ]);

        $this->assertSame('pending', $result['status']);
        $this->assertStringStartsWith('pay_', $result['order_no']);
        $this->assertStringStartsWith('https://qr.alipay.com/mock-', $result['payment_params']['qr_code']);
        $this->assertTrue($result['payment_params']['mock']);
    }

    public function test_alipay_notify_marks_order_paid_and_recharges_wallet_once(): void
    {
        config(['payment.alipay.mock' => true]);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $order = new InMemoryPaymentOrder('pay_alipay_1', 'pending');
        $order->channel = 'alipay';
        $wallet = new InMemoryPaymentWallet();
        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDao(),
            new InMemoryPaymentOrderDao($order),
            $wallet,
            new InMemoryPaymentIdentityDao()
        );

        $result = $business->alipayNotify([
            'out_trade_no' => 'pay_alipay_1',
            'trade_no' => 'ali_trade_1',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '9.90',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('paid', $order->status);
        $this->assertSame('ali_trade_1', $order->transaction_id);
        $this->assertSame(1, $wallet->rechargeCount);
        $this->assertSame('支付宝支付充值', $wallet->lastRecharge['remark']);
    }

    public function test_alipay_notify_rejects_amount_mismatch_without_recharge(): void
    {
        config(['payment.alipay.mock' => true]);
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $order = new InMemoryPaymentOrder('pay_alipay_amount', 'pending');
        $order->channel = 'alipay';
        $wallet = new InMemoryPaymentWallet();
        $business = new PaymentBusiness(
            new InMemoryPaymentPackageDao(),
            new InMemoryPaymentOrderDao($order),
            $wallet,
            new InMemoryPaymentIdentityDao()
        );

        $this->expectException(ValidationException::class);

        try {
            $business->alipayNotify([
                'out_trade_no' => 'pay_alipay_amount',
                'trade_status' => 'TRADE_SUCCESS',
                'total_amount' => '0.01',
            ]);
        } finally {
            $this->assertSame(0, $wallet->rechargeCount);
        }
    }
    public function test_default_antifraud_packages_seed_three_rows(): void
    {
        $database = '/private/tmp/storage_payment_packages_test.sqlite';
        @unlink($database);
        touch($database);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $database,
        ]);

        Artisan::call('migrate:fresh', ['--force' => true]);

        $packages = app(PaymentBusiness::class)->packages('antifraud');

        $this->assertCount(3, $packages);
        $this->assertSame([100, 260, 800], collect($packages)->pluck('points')->all());
        $this->assertSame(3, PaymentPackage::where('project_code', 'antifraud')->count());
    }
}

class InMemoryPaymentOrderDao extends PaymentOrderDao
{
    public function __construct(private InMemoryPaymentOrder $order)
    {
    }

    public function lockByOrderNo(string $orderNo): ?PaymentOrder
    {
        return $this->order->order_no === $orderNo ? $this->order : null;
    }
}

class InMemoryPaymentPackageDao extends PaymentPackageDao
{
    public function __construct()
    {
    }
}

class InMemoryPaymentPackageDaoWithPackage extends PaymentPackageDao
{
    public function __construct()
    {
    }

    public function find($id, array $columns = [])
    {
        $package = new PaymentPackage();
        $package->id = (int) $id;
        $package->project_code = 'antifraud';
        $package->name = '测试套餐';
        $package->points = 100;
        $package->amount_cent = 990;
        $package->enabled = 1;

        return $package;
    }
}

class InMemoryPaymentOrderStoreDao extends PaymentOrderDao
{
    public ?InMemoryPaymentOrder $storedOrder = null;

    public function __construct()
    {
    }

    public function store(array $params, array $extra = []): \Illuminate\Database\Eloquent\Model
    {
        $order = new InMemoryPaymentOrder((string) $params['order_no'], (string) $params['status']);
        $order->fill($params);
        $this->storedOrder = $order;

        return $order;
    }
}

class InMemoryPaymentIdentityDao extends AuthIdentityDao
{
    public function __construct()
    {
    }
}

class InMemoryPaymentWallet extends WalletBusiness
{
    public int $rechargeCount = 0;
    public array $lastRecharge = [];

    public function __construct()
    {
    }

    public function recharge(int $userId, string $projectCode, int $amount, string $relatedNo, string $remark = ''): array
    {
        $this->rechargeCount += 1;
        $this->lastRecharge = [
            'user_id' => $userId,
            'project_code' => $projectCode,
            'amount' => $amount,
            'related_no' => $relatedNo,
            'remark' => $remark,
        ];

        return ['balance' => $amount, 'frozen_balance' => 0];
    }
}

class InMemoryPaymentOrder extends PaymentOrder
{
    public $timestamps = false;
    public bool $saved = false;

    public function __construct(string $orderNo = '', string $status = 'pending')
    {
        parent::__construct();
        $this->exists = true;
        $this->order_no = $orderNo;
        $this->user_id = 10001;
        $this->project_code = 'antifraud';
        $this->package_id = 1;
        $this->points = 100;
        $this->amount_cent = 990;
        $this->channel = 'wechat';
        $this->status = $status;
    }

    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;

        return $this;
    }
}
