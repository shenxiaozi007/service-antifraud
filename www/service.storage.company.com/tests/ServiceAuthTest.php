<?php

namespace Tests;

use App\Modules\Service\Business\Auth\ServiceGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceAuthTest extends TestCase
{
    public function test_service_guard_accepts_valid_signature(): void
    {
        config(['service_auth.apps' => ['antifraud' => 'secret']]);
        $timestamp = (string) time();
        $request = Request::create('/service/api/v1/auth/introspect', 'POST', [], [], [], [
            'HTTP_X_SERVICE_APP_ID' => 'antifraud',
            'HTTP_X_SERVICE_TIMESTAMP' => $timestamp,
            'HTTP_X_SERVICE_SIGN' => hash_hmac('sha256', 'antifraud|'.$timestamp, 'secret'),
        ]);

        app(ServiceGuard::class)->verify($request);

        $this->assertTrue(true);
    }

    public function test_service_guard_rejects_invalid_signature(): void
    {
        config(['service_auth.apps' => ['antifraud' => 'secret']]);
        $this->expectException(HttpException::class);

        $request = Request::create('/service/api/v1/auth/introspect', 'POST', [], [], [], [
            'HTTP_X_SERVICE_APP_ID' => 'antifraud',
            'HTTP_X_SERVICE_TIMESTAMP' => (string) time(),
            'HTTP_X_SERVICE_SIGN' => 'bad',
        ]);

        app(ServiceGuard::class)->verify($request);
    }
}
