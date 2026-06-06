<?php

namespace App\Modules\Service\Business\Payment;

use App\Modules\Basics\Dao\Auth\AuthIdentityDao;
use App\Modules\Basics\Dao\Payment\PaymentOrderDao;
use App\Modules\Basics\Dao\Payment\PaymentPackageDao;
use App\Modules\Service\Business\Wallet\WalletBusiness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentBusiness
{
    public function __construct(
        protected PaymentPackageDao $packageDao,
        protected PaymentOrderDao $orderDao,
        protected WalletBusiness $walletBusiness,
        protected AuthIdentityDao $identityDao,
    ) {
    }

    public function packages(string $projectCode): array
    {
        $packages = $this->packageDao->enabledPackages($projectCode);

        if ($packages->isEmpty() && $projectCode === 'antifraud') {
            $this->seedDefaultPackages($projectCode);
            $packages = $this->packageDao->enabledPackages($projectCode);
        }

        return $packages->map(fn ($package) => [
            'id' => $package->id,
            'project_code' => $package->project_code,
            'name' => $package->name,
            'points' => $package->points,
            'amount_cent' => $package->amount_cent,
        ])->values()->all();
    }

    public function wechatJsapiOrder(int $userId, array $params): array
    {
        $data = validator($params, [
            'project_code' => 'required|string|max:64',
            'package_id' => 'required|integer|min:1',
            'openid' => 'nullable|string|max:128',
        ])->validate();

        $package = $this->packageDao->find((int) $data['package_id']);
        if (!$package || $package->project_code !== $data['project_code'] || (int) $package->enabled !== 1) {
            throw ValidationException::withMessages(['package_id' => '套餐不存在或已下架']);
        }

        $order = $this->orderDao->store([
            'order_no' => 'pay_'.date('YmdHis').Str::lower(Str::random(12)),
            'user_id' => $userId,
            'project_code' => $package->project_code,
            'package_id' => $package->id,
            'points' => $package->points,
            'amount_cent' => $package->amount_cent,
            'channel' => 'wechat',
            'status' => 'pending',
            'prepay_id' => '',
            'payment_params' => [],
        ]);

        $paymentParams = $this->createWechatPrepay($order, $this->paymentOpenid($userId, (string) ($data['openid'] ?? '')));
        $order->fill([
            'prepay_id' => $paymentParams['prepay_id'],
            'payment_params' => $paymentParams,
        ])->save();

        return [
            'order_no' => $order->order_no,
            'status' => $order->status,
            'payment_params' => $paymentParams,
        ];
    }

    public function wechatNotify(array $params, array $headers = [], string $rawBody = ''): array
    {
        $this->verifyWechatNotifySignature($headers, $rawBody);

        $params = $this->normalizeWechatNotify($params);
        $orderNo = $params['out_trade_no'] ?? $params['order_no'] ?? '';
        $transactionId = $params['transaction_id'] ?? '';
        if ($orderNo === '') {
            throw ValidationException::withMessages(['order_no' => '订单号不能为空']);
        }

        return DB::transaction(function () use ($params, $orderNo, $transactionId) {
            $order = $this->orderDao->lockByOrderNo($orderNo);
            if (!$order) {
                throw ValidationException::withMessages(['order_no' => '订单不存在']);
            }
            if ($order->status === 'paid') {
                return ['success' => true, 'order_no' => $order->order_no, 'status' => $order->status];
            }
            if ($order->status !== 'pending') {
                throw ValidationException::withMessages(['order_no' => '订单状态不允许入账']);
            }

            $this->assertWechatNotifyPaid($params, $order);

            $order->fill([
                'status' => 'paid',
                'transaction_id' => $transactionId,
                'notify_payload' => $params,
                'paid_at' => Carbon::now(),
            ])->save();

            $this->walletBusiness->recharge($order->user_id, $order->project_code, $order->points, $order->order_no, '微信支付充值');

            return ['success' => true, 'order_no' => $order->order_no, 'status' => $order->status];
        });
    }

    protected function assertWechatNotifyPaid(array $params, $order): void
    {
        $tradeState = (string) ($params['trade_state'] ?? 'SUCCESS');
        if ($tradeState !== 'SUCCESS') {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付未成功：'.$tradeState]);
        }

        $amount = $params['amount']['total'] ?? null;
        if ($amount !== null && (int) $amount !== (int) $order->amount_cent) {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付金额不一致']);
        }

        $payerTotal = $params['amount']['payer_total'] ?? null;
        if ($payerTotal !== null && (int) $payerTotal !== (int) $order->amount_cent) {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付实付金额不一致']);
        }
    }

    protected function verifyWechatNotifySignature(array $headers, string $rawBody): void
    {
        if ($this->shouldMockWechatPay()) {
            return;
        }

        $timestamp = $this->firstHeader($headers, 'wechatpay-timestamp');
        $nonce = $this->firstHeader($headers, 'wechatpay-nonce');
        $signature = $this->firstHeader($headers, 'wechatpay-signature');
        if ($timestamp === '' || $nonce === '' || $signature === '' || $rawBody === '') {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付回调签名参数不完整']);
        }

        $certificate = $this->wechatPlatformCertificate();
        if ($certificate === '') {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付平台证书未配置']);
        }

        $message = implode("\n", [$timestamp, $nonce, $rawBody, '']);
        $verified = openssl_verify($message, base64_decode($signature, true), $certificate, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付回调签名验证失败']);
        }
    }

    protected function firstHeader(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }

            return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
        }

        return '';
    }

    protected function normalizeWechatNotify(array $params): array
    {
        if (!isset($params['resource']) || !is_array($params['resource'])) {
            return $params;
        }

        $resource = $params['resource'];
        $ciphertext = (string) ($resource['ciphertext'] ?? '');
        $nonce = (string) ($resource['nonce'] ?? '');
        $associatedData = (string) ($resource['associated_data'] ?? '');
        $apiKey = (string) config('payment.wechat.api_v3_key', '');
        if ($ciphertext === '' || $nonce === '' || $apiKey === '') {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付回调解密参数不完整']);
        }

        $decoded = base64_decode($ciphertext, true);
        $tag = substr($decoded, -16);
        $cipher = substr($decoded, 0, -16);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $apiKey, OPENSSL_RAW_DATA, $nonce, $tag, $associatedData);
        $data = json_decode((string) $plain, true);
        if (!is_array($data)) {
            throw ValidationException::withMessages(['wechat_notify' => '微信支付回调解密失败']);
        }

        return array_merge($params, $data);
    }

    protected function createWechatPrepay($order, string $openid): array
    {
        if ($this->shouldMockWechatPay()) {
            return $this->mockWechatPaymentParams($order->order_no);
        }

        $this->assertWechatPayConfigReady();

        if ($openid === '') {
            throw ValidationException::withMessages(['openid' => '微信支付需要 openid']);
        }

        $path = '/v3/pay/transactions/jsapi';
        $body = [
            'appid' => config('payment.wechat.app_id'),
            'mchid' => config('payment.wechat.mch_id'),
            'description' => '反诈助手点数充值',
            'out_trade_no' => $order->order_no,
            'notify_url' => config('payment.wechat.notify_url'),
            'amount' => ['total' => (int) $order->amount_cent, 'currency' => 'CNY'],
            'payer' => ['openid' => $openid],
        ];
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders(['Authorization' => $this->wechatAuthorization('POST', $path, $json)])
            ->post(rtrim(config('payment.wechat.api_base_url'), '/').$path, $body);

        if (!$response->successful()) {
            throw ValidationException::withMessages(['wechat_pay' => '微信支付下单失败：'.$response->body()]);
        }

        $prepayId = (string) ($response->json('prepay_id') ?? '');
        if ($prepayId === '') {
            throw ValidationException::withMessages(['wechat_pay' => '微信支付未返回 prepay_id']);
        }

        return $this->buildWechatPaymentParams($prepayId, false);
    }

    protected function paymentOpenid(int $userId, string $openid): string
    {
        if ($openid !== '') {
            return $openid;
        }

        $identity = $this->identityDao->findUserIdentity($userId, 'wechat');

        return (string) ($identity?->identifier ?? '');
    }

    protected function mockWechatPaymentParams(string $orderNo): array
    {
        $prepayId = 'mock_prepay_'.$orderNo;
        return $this->buildWechatPaymentParams($prepayId, true);
    }

    protected function buildWechatPaymentParams(string $prepayId, bool $mock): array
    {
        $timestamp = (string) time();
        $nonce = Str::random(16);
        $package = 'prepay_id='.$prepayId;
        $message = implode("\n", [config('payment.wechat.app_id', ''), $timestamp, $nonce, $package, '']);

        return [
            'appId' => config('payment.wechat.app_id', ''),
            'timeStamp' => $timestamp,
            'nonceStr' => $nonce,
            'package' => $package,
            'signType' => 'RSA',
            'paySign' => $this->rsaSign($message),
            'prepay_id' => $prepayId,
            'mock' => $mock,
        ];
    }

    protected function shouldMockWechatPay(): bool
    {
        return (bool) config('payment.wechat.mock', false);
    }

    protected function assertWechatPayConfigReady(): void
    {
        $missing = [];
        foreach ([
            'app_id' => 'WECHAT_PAY_APP_ID',
            'mch_id' => 'WECHAT_PAY_MCH_ID',
            'api_v3_key' => 'WECHAT_PAY_API_V3_KEY',
            'merchant_serial_no' => 'WECHAT_PAY_MERCHANT_SERIAL_NO',
            'notify_url' => 'WECHAT_PAY_NOTIFY_URL',
        ] as $key => $envName) {
            if ((string) config('payment.wechat.'.$key, '') === '') {
                $missing[] = $envName;
            }
        }

        if ($this->merchantPrivateKey() === '') {
            $missing[] = 'WECHAT_PAY_MERCHANT_PRIVATE_KEY 或 WECHAT_PAY_MERCHANT_PRIVATE_KEY_PATH';
        }

        if ($missing) {
            throw ValidationException::withMessages(['wechat_pay' => '微信支付配置缺失：'.implode('、', $missing)]);
        }
    }

    protected function wechatAuthorization(string $method, string $path, string $body): string
    {
        $timestamp = (string) time();
        $nonce = Str::random(16);
        $message = implode("\n", [$method, $path, $timestamp, $nonce, $body, '']);
        $signature = $this->rsaSign($message);

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%s",serial_no="%s",signature="%s"',
            config('payment.wechat.mch_id'),
            $nonce,
            $timestamp,
            config('payment.wechat.merchant_serial_no'),
            $signature
        );
    }

    protected function rsaSign(string $message): string
    {
        $key = $this->merchantPrivateKey();
        if ($key === '') {
            return hash('sha256', $message);
        }

        openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    protected function merchantPrivateKey(): string
    {
        $key = (string) config('payment.wechat.merchant_private_key', '');
        $path = (string) config('payment.wechat.merchant_private_key_path', '');
        if ($key === '' && $path !== '' && is_readable($path)) {
            $key = (string) file_get_contents($path);
        }

        return str_replace('\\n', "\n", $key);
    }

    protected function wechatPlatformCertificate(): string
    {
        $certificate = (string) config('payment.wechat.platform_certificate', '');
        $path = (string) config('payment.wechat.platform_certificate_path', '');
        if ($certificate === '' && $path !== '' && is_readable($path)) {
            $certificate = (string) file_get_contents($path);
        }

        return str_replace('\\n', "\n", $certificate);
    }

    protected function seedDefaultPackages(string $projectCode): void
    {
        foreach ([
            ['name' => '基础点数包', 'points' => 100, 'amount_cent' => 990, 'sort' => 10],
            ['name' => '常用点数包', 'points' => 260, 'amount_cent' => 1990, 'sort' => 20],
            ['name' => '专业点数包', 'points' => 800, 'amount_cent' => 4990, 'sort' => 30],
        ] as $item) {
            $this->packageDao->store([
                'project_code' => $projectCode,
                'name' => $item['name'],
                'points' => $item['points'],
                'amount_cent' => $item['amount_cent'],
                'enabled' => 1,
                'sort' => $item['sort'],
            ]);
        }
    }
}
