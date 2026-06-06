<?php

namespace App\Libraries\CommonService;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CommonServiceClient
{
    public function introspect(string $token): array
    {
        return $this->request('post', 'auth/introspect', [], $token);
    }

    public function wechatLogin(array $params): array
    {
        return $this->request('post', 'auth/wechat-login', $params);
    }

    public function sendCode(array $params): array
    {
        return $this->request('post', 'auth/send-code', $params);
    }

    public function codeLogin(array $params): array
    {
        return $this->request('post', 'auth/code-login', $params);
    }

    public function balance(string $token): array
    {
        return $this->request('get', 'wallet/balance', ['project_code' => $this->projectCode()], $token);
    }

    public function transactions(string $token, array $params): array
    {
        return $this->request('get', 'wallet/transactions', array_merge(['project_code' => $this->projectCode()], $params), $token);
    }

    public function transactionsByUser(int $globalUserId, array $params = []): array
    {
        return $this->request('get', 'wallet/transactions-by-user', array_merge([
            'user_id' => $globalUserId,
            'project_code' => $this->projectCode(),
        ], $params));
    }

    public function freeze(int $globalUserId, int $amount, string $relatedNo, string $remark): array
    {
        return $this->request('post', 'wallet/freeze', [
            'user_id' => $globalUserId,
            'project_code' => $this->projectCode(),
            'amount' => $amount,
            'related_no' => $relatedNo,
            'remark' => $remark,
        ]);
    }

    public function confirm(int $globalUserId, int $amount, string $relatedNo, string $remark): array
    {
        return $this->request('post', 'wallet/confirm', [
            'user_id' => $globalUserId,
            'project_code' => $this->projectCode(),
            'amount' => $amount,
            'related_no' => $relatedNo,
            'remark' => $remark,
        ]);
    }

    public function release(int $globalUserId, int $amount, string $relatedNo, string $remark): array
    {
        return $this->request('post', 'wallet/release', [
            'user_id' => $globalUserId,
            'project_code' => $this->projectCode(),
            'amount' => $amount,
            'related_no' => $relatedNo,
            'remark' => $remark,
        ]);
    }

    public function paymentPackages(array $params = []): array
    {
        return $this->request('get', 'payment/packages', array_merge(['project_code' => $this->projectCode()], $params));
    }

    public function wechatOrder(string $token, array $params): array
    {
        return $this->request('post', 'payment/wechat/jsapi-order', array_merge(['project_code' => $this->projectCode()], $params), $token);
    }

    protected function request(string $method, string $path, array $params = [], string $token = ''): array
    {
        $url = rtrim(config('common_service.base_url'), '/').'/'.ltrim($path, '/');
        $http = Http::timeout((int) config('common_service.timeout', 15))
            ->acceptJson()
            ->withHeaders($this->headers($token));

        $host = (string) config('common_service.host', '');
        if ($host !== '') {
            $http = $http->withHeaders(['Host' => $host]);
        }

        $response = $method === 'get' ? $http->get($url, $params) : $http->post($url, $params);
        $body = $response->json();

        if (!$response->successful() || !is_array($body) || (int) ($body['code'] ?? 500) !== 0) {
            throw new HttpException($response->status() ?: 502, $body['message'] ?? '公共服务请求失败');
        }

        return $body['data'] ?? [];
    }

    protected function headers(string $token): array
    {
        $timestamp = (string) time();
        $appId = (string) config('common_service.service_app_id');
        $secret = (string) config('common_service.service_secret');
        $headers = [
            'X-Service-App-Id' => $appId,
            'X-Service-Timestamp' => $timestamp,
            'X-Service-Sign' => hash_hmac('sha256', $appId.'|'.$timestamp, $secret),
        ];

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $headers;
    }

    protected function projectCode(): string
    {
        return (string) config('common_service.project_code', 'antifraud');
    }
}
