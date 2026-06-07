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

    /**
     * 获取指定项目的可用充值套餐。
     *
     * @param string $projectCode 项目编码
     * @return array<int, array{id:int,project_code:string,name:string,points:int,amount_cent:int}>
     */
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

    /**
     * 创建微信 JSAPI 支付订单。
     *
     * @param int $userId 公共用户ID
     * @param array $params 下单参数，包含 project_code/package_id/openid
     * @return array{order_no:string,status:string,payment_params:array}
     */
    public function wechatJsapiOrder(int $userId, array $params): array
    {
        $data = validator($params, [
            'project_code' => 'required|string|max:64',
            'package_id' => 'required|integer|min:1',
            'openid' => 'nullable|string|max:128',
        ])->validate();

        $order = $this->createPendingOrder($userId, $data, 'wechat');
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

    /**
     * 创建支付宝扫码预下单订单。
     *
     * @param int $userId 公共用户ID
     * @param array $params 下单参数，包含 project_code/package_id
     * @return array{order_no:string,status:string,payment_params:array}
     */
    public function alipayPrecreateOrder(int $userId, array $params): array
    {
        $data = validator($params, [
            'project_code' => 'required|string|max:64',
            'package_id' => 'required|integer|min:1',
        ])->validate();

        $order = $this->createPendingOrder($userId, $data, 'alipay');
        $paymentParams = $this->createAlipayPrecreate($order);
        $order->fill([
            'prepay_id' => $paymentParams['trade_no'] ?? '',
            'payment_params' => $paymentParams,
        ])->save();

        return [
            'order_no' => $order->order_no,
            'status' => $order->status,
            'payment_params' => $paymentParams,
        ];
    }

    /**
     * 查询当前用户的支付订单状态，用于前端扫码支付轮询。
     *
     * @param int $userId 公共用户ID
     * @param string $orderNo 支付订单号
     * @return array{order_no:string,status:string,channel:string,amount_cent:int,points:int,paid_at:string|null}
     */
    public function orderStatus(int $userId, string $orderNo): array
    {
        $order = $this->orderDao->findByOrderNo($orderNo);
        if (!$order || (int) $order->user_id !== $userId) {
            throw ValidationException::withMessages(['order_no' => '订单不存在']);
        }

        return [
            'order_no' => $order->order_no,
            'status' => $order->status,
            'channel' => $order->channel,
            'amount_cent' => (int) $order->amount_cent,
            'points' => (int) $order->points,
            'paid_at' => $order->paid_at ? $order->paid_at->toDateTimeString() : null,
        ];
    }

    /**
     * 处理微信支付回调并完成钱包入账。
     *
     * @param array $params 回调参数
     * @param array $headers 回调请求头
     * @param string $rawBody 原始回调内容
     * @return array{success:bool,order_no:string,status:string}
     */
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

            $this->assertOrderCanBePaid($order);
            if ($order->status === 'paid') {
                return ['success' => true, 'order_no' => $order->order_no, 'status' => $order->status];
            }

            $this->assertWechatNotifyPaid($params, $order);

            return $this->markOrderPaidAndRecharge($order, $transactionId, $params, '微信支付充值');
        });
    }

    /**
     * 处理支付宝异步回调并完成钱包入账。
     *
     * @param array $params 支付宝回调参数
     * @return array{success:bool,order_no:string,status:string}
     */
    public function alipayNotify(array $params): array
    {
        $this->verifyAlipayNotifySignature($params);

        $orderNo = (string) ($params['out_trade_no'] ?? '');
        $transactionId = (string) ($params['trade_no'] ?? '');
        if ($orderNo === '') {
            throw ValidationException::withMessages(['order_no' => '订单号不能为空']);
        }

        return DB::transaction(function () use ($params, $orderNo, $transactionId) {
            $order = $this->orderDao->lockByOrderNo($orderNo);
            if (!$order) {
                throw ValidationException::withMessages(['order_no' => '订单不存在']);
            }

            $this->assertOrderCanBePaid($order);
            if ($order->status === 'paid') {
                return ['success' => true, 'order_no' => $order->order_no, 'status' => $order->status];
            }

            $this->assertAlipayNotifyPaid($params, $order);

            return $this->markOrderPaidAndRecharge($order, $transactionId, $params, '支付宝支付充值');
        });
    }

    /**
     * 创建待支付订单并校验套餐状态。
     *
     * @param int $userId 公共用户ID
     * @param array $data 已校验下单参数
     * @param string $channel 支付渠道
     * @return mixed 支付订单模型
     */
    protected function createPendingOrder(int $userId, array $data, string $channel)
    {
        $package = $this->packageDao->find((int) $data['package_id']);
        if (!$package || $package->project_code !== $data['project_code'] || (int) $package->enabled !== 1) {
            throw ValidationException::withMessages(['package_id' => '套餐不存在或已下架']);
        }

        return $this->orderDao->store([
            'order_no' => 'pay_'.date('YmdHis').Str::lower(Str::random(12)),
            'user_id' => $userId,
            'project_code' => $package->project_code,
            'package_id' => $package->id,
            'points' => $package->points,
            'amount_cent' => $package->amount_cent,
            'channel' => $channel,
            'status' => 'pending',
            'prepay_id' => '',
            'payment_params' => [],
        ]);
    }

    /**
     * 校验订单状态是否允许支付回调入账。
     *
     * @param mixed $order 支付订单模型
     * @return void
     */
    protected function assertOrderCanBePaid($order): void
    {
        if ($order->status === 'paid') {
            return;
        }

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages(['order_no' => '订单状态不允许入账']);
        }
    }

    /**
     * 标记订单已支付并给钱包充值。
     *
     * @param mixed $order 支付订单模型
     * @param string $transactionId 第三方支付流水号
     * @param array $payload 回调载荷
     * @param string $remark 钱包流水备注
     * @return array{success:bool,order_no:string,status:string}
     */
    protected function markOrderPaidAndRecharge($order, string $transactionId, array $payload, string $remark): array
    {
        $order->fill([
            'status' => 'paid',
            'transaction_id' => $transactionId,
            'notify_payload' => $payload,
            'paid_at' => Carbon::now(),
        ])->save();

        $this->walletBusiness->recharge($order->user_id, $order->project_code, $order->points, $order->order_no, $remark);

        return ['success' => true, 'order_no' => $order->order_no, 'status' => $order->status];
    }

    /**
     * 校验微信回调的支付状态和金额。
     *
     * @param array $params 微信回调参数
     * @param mixed $order 支付订单模型
     * @return void
     */
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

    /**
     * 校验支付宝回调的支付状态和金额。
     *
     * @param array $params 支付宝回调参数
     * @param mixed $order 支付订单模型
     * @return void
     */
    protected function assertAlipayNotifyPaid(array $params, $order): void
    {
        $tradeStatus = (string) ($params['trade_status'] ?? 'TRADE_SUCCESS');
        if (!in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            throw ValidationException::withMessages(['alipay_notify' => '支付宝支付未成功：'.$tradeStatus]);
        }

        $totalAmount = $params['total_amount'] ?? null;
        if ($totalAmount !== null && $this->amountYuanToCent((string) $totalAmount) !== (int) $order->amount_cent) {
            throw ValidationException::withMessages(['alipay_notify' => '支付宝支付金额不一致']);
        }
    }

    /**
     * 校验微信回调签名。
     *
     * @param array $headers 回调请求头
     * @param string $rawBody 原始请求内容
     * @return void
     */
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

    /**
     * 从请求头数组中读取指定头部值。
     *
     * @param array $headers 请求头数组
     * @param string $name 小写头部名称
     * @return string
     */
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

    /**
     * 解密微信支付 v3 回调资源载荷。
     *
     * @param array $params 回调参数
     * @return array
     */
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

    /**
     * 调用微信支付 JSAPI 预下单。
     *
     * @param mixed $order 支付订单模型
     * @param string $openid 微信 openid
     * @return array
     */
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

    /**
     * 调用支付宝当面付预下单并返回二维码链接。
     *
     * @param mixed $order 支付订单模型
     * @return array{qr_code:string,order_no:string,trade_no:string,mock:bool}
     */
    protected function createAlipayPrecreate($order): array
    {
        if ($this->shouldMockAlipay()) {
            return $this->mockAlipayPaymentParams($order->order_no);
        }

        $this->assertAlipayConfigReady();

        $bizContent = [
            'out_trade_no' => $order->order_no,
            'total_amount' => $this->formatAmountYuan((int) $order->amount_cent),
            'subject' => '反诈助手点数充值',
            'timeout_express' => '30m',
        ];
        $params = $this->alipayBaseParams('alipay.trade.precreate', $bizContent);
        $params['sign'] = $this->alipaySign($params);

        $response = Http::asForm()->timeout(15)->post((string) config('payment.alipay.gateway_url'), $params);
        if (!$response->successful()) {
            throw ValidationException::withMessages(['alipay_pay' => '支付宝下单失败：'.$response->body()]);
        }

        $body = $response->json();
        $result = is_array($body) ? ($body['alipay_trade_precreate_response'] ?? []) : [];
        if (($result['code'] ?? '') !== '10000' || empty($result['qr_code'])) {
            throw ValidationException::withMessages(['alipay_pay' => '支付宝未返回二维码：'.($result['sub_msg'] ?? $result['msg'] ?? '未知错误')]);
        }

        return [
            'qr_code' => (string) $result['qr_code'],
            'order_no' => $order->order_no,
            'trade_no' => (string) ($result['trade_no'] ?? ''),
            'mock' => false,
        ];
    }

    /**
     * 获取用户微信支付 openid。
     *
     * @param int $userId 公共用户ID
     * @param string $openid 请求显式传入的 openid
     * @return string
     */
    protected function paymentOpenid(int $userId, string $openid): string
    {
        if ($openid !== '') {
            return $openid;
        }

        $identity = $this->identityDao->findUserIdentity($userId, 'wechat');

        return (string) ($identity?->identifier ?? '');
    }

    /**
     * 生成微信支付 mock 参数。
     *
     * @param string $orderNo 订单号
     * @return array
     */
    protected function mockWechatPaymentParams(string $orderNo): array
    {
        $prepayId = 'mock_prepay_'.$orderNo;
        return $this->buildWechatPaymentParams($prepayId, true);
    }

    /**
     * 生成支付宝支付 mock 参数。
     *
     * @param string $orderNo 订单号
     * @return array{qr_code:string,order_no:string,trade_no:string,mock:bool}
     */
    protected function mockAlipayPaymentParams(string $orderNo): array
    {
        return [
            'qr_code' => 'https://qr.alipay.com/mock-'.$orderNo,
            'order_no' => $orderNo,
            'trade_no' => 'mock_trade_'.$orderNo,
            'mock' => true,
        ];
    }

    /**
     * 组装前端调起微信支付参数。
     *
     * @param string $prepayId 微信预支付ID
     * @param bool $mock 是否 mock 模式
     * @return array
     */
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

    /**
     * 判断是否启用微信支付 mock。
     *
     * @return bool
     */
    protected function shouldMockWechatPay(): bool
    {
        return (bool) config('payment.wechat.mock', false);
    }

    /**
     * 判断是否启用支付宝支付 mock。
     *
     * @return bool
     */
    protected function shouldMockAlipay(): bool
    {
        return (bool) config('payment.alipay.mock', false);
    }

    /**
     * 校验微信支付配置完整性。
     *
     * @return void
     */
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

    /**
     * 校验支付宝支付配置完整性。
     *
     * @return void
     */
    protected function assertAlipayConfigReady(): void
    {
        $missing = [];
        foreach ([
            'app_id' => 'ALIPAY_APP_ID',
            'gateway_url' => 'ALIPAY_GATEWAY_URL',
            'notify_url' => 'ALIPAY_NOTIFY_URL',
        ] as $key => $envName) {
            if ((string) config('payment.alipay.'.$key, '') === '') {
                $missing[] = $envName;
            }
        }

        if ($this->alipayAppPrivateKey() === '') {
            $missing[] = 'ALIPAY_APP_PRIVATE_KEY 或 ALIPAY_APP_PRIVATE_KEY_PATH';
        }

        if ($this->alipayPublicKey() === '') {
            $missing[] = 'ALIPAY_PUBLIC_KEY 或 ALIPAY_PUBLIC_KEY_PATH';
        }

        if ($missing) {
            throw ValidationException::withMessages(['alipay_pay' => '支付宝配置缺失：'.implode('、', $missing)]);
        }
    }

    /**
     * 构造微信支付 Authorization 头。
     *
     * @param string $method HTTP 方法
     * @param string $path 请求路径
     * @param string $body 请求 JSON 字符串
     * @return string
     */
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

    /**
     * 使用微信商户私钥生成 RSA 签名。
     *
     * @param string $message 待签名字符串
     * @return string
     */
    protected function rsaSign(string $message): string
    {
        $key = $this->merchantPrivateKey();
        if ($key === '') {
            return hash('sha256', $message);
        }

        openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * 读取微信商户私钥内容。
     *
     * @return string
     */
    protected function merchantPrivateKey(): string
    {
        $key = (string) config('payment.wechat.merchant_private_key', '');
        $path = (string) config('payment.wechat.merchant_private_key_path', '');
        if ($key === '' && $path !== '' && is_readable($path)) {
            $key = (string) file_get_contents($path);
        }

        return str_replace('\\n', "\n", $key);
    }

    /**
     * 读取微信平台证书内容。
     *
     * @return string
     */
    protected function wechatPlatformCertificate(): string
    {
        $certificate = (string) config('payment.wechat.platform_certificate', '');
        $path = (string) config('payment.wechat.platform_certificate_path', '');
        if ($certificate === '' && $path !== '' && is_readable($path)) {
            $certificate = (string) file_get_contents($path);
        }

        return str_replace('\\n', "\n", $certificate);
    }

    /**
     * 构造支付宝开放平台公共请求参数。
     *
     * @param string $method 支付宝接口方法
     * @param array $bizContent 业务参数
     * @return array
     */
    protected function alipayBaseParams(string $method, array $bizContent): array
    {
        $params = [
            'app_id' => config('payment.alipay.app_id'),
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => config('payment.alipay.notify_url'),
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $returnUrl = (string) config('payment.alipay.return_url', '');
        if ($returnUrl !== '') {
            $params['return_url'] = $returnUrl;
        }

        return $params;
    }

    /**
     * 生成支付宝 RSA2 签名。
     *
     * @param array $params 待签名参数
     * @return string
     */
    protected function alipaySign(array $params): string
    {
        $content = $this->alipaySignContent($params);
        openssl_sign($content, $signature, $this->alipayAppPrivateKey(), OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * 校验支付宝异步通知签名。
     *
     * @param array $params 支付宝回调参数
     * @return void
     */
    protected function verifyAlipayNotifySignature(array $params): void
    {
        if ($this->shouldMockAlipay()) {
            return;
        }

        $signature = (string) ($params['sign'] ?? '');
        if ($signature === '') {
            throw ValidationException::withMessages(['alipay_notify' => '支付宝回调签名缺失']);
        }

        $verified = openssl_verify($this->alipaySignContent($params), base64_decode($signature, true), $this->alipayPublicKey(), OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw ValidationException::withMessages(['alipay_notify' => '支付宝回调签名验证失败']);
        }
    }

    /**
     * 生成支付宝待签名字符串。
     *
     * @param array $params 支付宝参数
     * @return string
     */
    protected function alipaySignContent(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null || is_array($value)) {
                continue;
            }

            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs);
    }

    /**
     * 读取支付宝应用私钥内容。
     *
     * @return string
     */
    protected function alipayAppPrivateKey(): string
    {
        return $this->readConfiguredKey('payment.alipay.app_private_key', 'payment.alipay.app_private_key_path');
    }

    /**
     * 读取支付宝公钥内容。
     *
     * @return string
     */
    protected function alipayPublicKey(): string
    {
        return $this->readConfiguredKey('payment.alipay.alipay_public_key', 'payment.alipay.alipay_public_key_path');
    }

    /**
     * 从配置值或配置文件路径中读取密钥内容。
     *
     * @param string $valueKey 配置值 key
     * @param string $pathKey 配置路径 key
     * @return string
     */
    protected function readConfiguredKey(string $valueKey, string $pathKey): string
    {
        $key = (string) config($valueKey, '');
        $path = (string) config($pathKey, '');
        if ($key === '' && $path !== '' && is_readable($path)) {
            $key = (string) file_get_contents($path);
        }

        return str_replace('\\n', "\n", $key);
    }

    /**
     * 将分转换为支付宝需要的元字符串。
     *
     * @param int $amountCent 金额，单位分
     * @return string
     */
    protected function formatAmountYuan(int $amountCent): string
    {
        return number_format($amountCent / 100, 2, '.', '');
    }

    /**
     * 将支付宝元金额字符串转换为分。
     *
     * @param string $amountYuan 金额，单位元
     * @return int
     */
    protected function amountYuanToCent(string $amountYuan): int
    {
        return (int) round(((float) $amountYuan) * 100);
    }

    /**
     * 初始化反诈项目默认套餐。
     *
     * @param string $projectCode 项目编码
     * @return void
     */
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
