<?php

namespace Tests;

use App\Libraries\CommonService\CommonServiceClient;

class CommonServiceClientTest extends TestCase
{
    public function test_common_service_client_builds_service_signature_headers(): void
    {
        config([
            'common_service.service_app_id' => 'antifraud',
            'common_service.service_secret' => 'secret',
        ]);

        $method = new \ReflectionMethod(CommonServiceClient::class, 'headers');
        $method->setAccessible(true);
        $headers = $method->invoke(app(CommonServiceClient::class), 'public-token');
        $expectedSign = hash_hmac('sha256', 'antifraud|'.$headers['X-Service-Timestamp'], 'secret');

        $this->assertSame('antifraud', $headers['X-Service-App-Id']);
        $this->assertSame($expectedSign, $headers['X-Service-Sign']);
        $this->assertSame('Bearer public-token', $headers['Authorization']);
    }

    public function test_common_service_client_uses_configured_project_code(): void
    {
        config([
            'common_service.project_code' => 'antifraud',
        ]);

        $method = new \ReflectionMethod(CommonServiceClient::class, 'projectCode');
        $method->setAccessible(true);

        $this->assertSame('antifraud', $method->invoke(app(CommonServiceClient::class)));
    }

    public function test_common_service_client_queries_wallet_transactions_by_global_user_id_with_service_auth(): void
    {
        config([
            'common_service.project_code' => 'antifraud',
        ]);

        $client = new InMemoryCommonServiceClientForTransactions();
        $result = $client->transactionsByUser(20001, ['page_size' => 10]);

        $this->assertSame(10, $result['page_size']);
        $this->assertSame('get', $client->lastMethod);
        $this->assertSame('wallet/transactions-by-user', $client->lastPath);
        $this->assertSame([
            'user_id' => 20001,
            'project_code' => 'antifraud',
            'page_size' => 10,
        ], $client->lastParams);
        $this->assertSame('', $client->lastToken);
    }

    public function test_common_service_client_queries_file_download_url_with_service_auth(): void
    {
        $client = new InMemoryCommonServiceClientForTransactions();
        $result = $client->fileDownloadUrl('file_123', 900);

        $this->assertSame('https://signed.example.com/file_123', $result['download_url']);
        $this->assertSame('get', $client->lastMethod);
        $this->assertSame('file/download-url', $client->lastPath);
        $this->assertSame([
            'file_id' => 'file_123',
            'expires' => 900,
        ], $client->lastParams);
        $this->assertSame('', $client->lastToken);
    }

    public function test_common_service_client_proxies_new_auth_and_alipay_paths(): void
    {
        config(['common_service.project_code' => 'antifraud']);

        $client = new InMemoryCommonServiceClientForTransactions();

        $client->passwordRegister(['account' => 'new@example.com']);
        $this->assertSame('post', $client->lastMethod);
        $this->assertSame('auth/password-register', $client->lastPath);

        $client->passwordLogin(['account' => 'new@example.com']);
        $this->assertSame('post', $client->lastMethod);
        $this->assertSame('auth/password-login', $client->lastPath);

        $client->alipayOrder('token-1', ['package_id' => 1]);
        $this->assertSame('post', $client->lastMethod);
        $this->assertSame('payment/alipay/precreate-order', $client->lastPath);
        $this->assertSame(['project_code' => 'antifraud', 'package_id' => 1], $client->lastParams);
        $this->assertSame('token-1', $client->lastToken);

        $client->paymentOrder('token-1', 'pay_123');
        $this->assertSame('get', $client->lastMethod);
        $this->assertSame('payment/orders/pay_123', $client->lastPath);
        $this->assertSame('token-1', $client->lastToken);
    }

}
class InMemoryCommonServiceClientForTransactions extends CommonServiceClient
{
    public string $lastPath = '';
    public array $lastParams = [];
    public string $lastToken = '';

    protected function request(string $method, string $path, array $params = [], string $token = ''): array
    {
        $this->lastMethod = $method;
        $this->lastPath = $path;
        $this->lastParams = $params;
        $this->lastToken = $token;

        if ($path === 'file/download-url') {
            return ['download_url' => 'https://signed.example.com/'.$params['file_id']];
        }

        return ['items' => [], 'total' => 0, 'page' => 1, 'page_size' => $params['page_size'] ?? 20];
    }
}
