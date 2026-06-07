<?php

namespace Tests;

use App\Modules\Basics\Dao\Auth\AuthIdentityDao;
use App\Modules\Basics\Dao\Auth\AuthTokenDao;
use App\Modules\Basics\Dao\Auth\CommonUserDao;
use App\Modules\Basics\Dao\Auth\VerificationCodeDao;
use App\Modules\Basics\Model\Auth\VerificationCode;
use App\Modules\Service\Business\Auth\AuthBusiness;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthBusinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = '/private/tmp/storage_auth_business_test.sqlite';
        @unlink($database);
        touch($database);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $database,
            'app.env' => 'testing',
            'verification.webhook_url' => '',
            'verification.webhook_token' => '',
        ]);

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_code_login_creates_user_identity_token_and_introspects_token(): void
    {
        $business = app(AuthBusiness::class);
        $account = 'mvp-test@example.com';

        $sent = $business->sendCode(['account' => $account, 'scene' => 'login']);
        $login = $business->codeLogin([
            'account' => $account,
            'code' => $sent['debug_code'],
            'scene' => 'login',
        ]);

        $this->assertNotEmpty($login['token']);
        $this->assertTrue($login['is_new_user']);
        $this->assertSame('用户'.substr(md5($account), 0, 6), $login['user']['nickname']);

        $introspected = $business->introspect($login['token']);
        $this->assertTrue($introspected['active']);
        $this->assertSame($login['user']['id'], $introspected['user']['id']);
    }

    public function test_password_register_login_and_introspect_work_for_email_account(): void
    {
        $business = app(AuthBusiness::class);
        $account = 'password-user@example.com';

        $registered = $business->passwordRegister([
            'account' => $account,
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
            'nickname' => '密码用户',
        ]);
        $login = $business->passwordLogin([
            'account' => $account,
            'password' => 'abc12345',
        ]);

        $this->assertNotEmpty($registered['token']);
        $this->assertTrue($registered['is_new_user']);
        $this->assertSame('密码用户', $registered['user']['nickname']);
        $this->assertNotEmpty($login['token']);
        $this->assertSame($registered['user']['id'], $login['user']['id']);
        $this->assertTrue($business->introspect($login['token'])['active']);
    }

    public function test_password_register_rejects_duplicate_password_account(): void
    {
        $business = app(AuthBusiness::class);
        $params = [
            'account' => 'duplicate-password@example.com',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ];

        $business->passwordRegister($params);

        $this->expectException(ValidationException::class);

        $business->passwordRegister($params);
    }

    public function test_password_login_rejects_wrong_password(): void
    {
        $business = app(AuthBusiness::class);
        $business->passwordRegister([
            'account' => 'wrong-password@example.com',
            'password' => 'abc12345',
            'password_confirmation' => 'abc12345',
        ]);

        $this->expectException(ValidationException::class);

        $business->passwordLogin([
            'account' => 'wrong-password@example.com',
            'password' => 'abc123456',
        ]);
    }
    public function test_wechat_login_reuses_openid_identity(): void
    {
        $business = app(AuthBusiness::class);

        $first = $business->wechatLogin([
            'openid' => 'openid-mvp-test',
            'unionid' => 'unionid-mvp-test',
            'nickname' => '微信测试用户',
        ]);
        $second = $business->wechatLogin([
            'openid' => 'openid-mvp-test',
            'unionid' => 'unionid-mvp-test',
            'nickname' => '微信测试用户',
        ]);

        $this->assertTrue($first['is_new_user']);
        $this->assertFalse($second['is_new_user']);
        $this->assertSame($first['user']['id'], $second['user']['id']);
    }

    public function test_wechat_login_mock_requires_explicit_mock_switch(): void
    {
        config([
            'wechat.mini_program.mock' => true,
            'wechat.mini_program.app_id' => '',
            'wechat.mini_program.app_secret' => '',
        ]);

        $login = app(AuthBusiness::class)->wechatLogin([
            'code' => 'dev-code',
            'nickname' => '本地微信用户',
        ]);

        $this->assertNotEmpty($login['token']);
        $this->assertTrue($login['is_new_user']);
    }

    public function test_wechat_login_requires_real_config_when_mock_is_disabled(): void
    {
        config([
            'wechat.mini_program.mock' => false,
            'wechat.mini_program.app_id' => '',
            'wechat.mini_program.app_secret' => '',
        ]);

        $this->expectException(ValidationException::class);

        app(AuthBusiness::class)->wechatLogin([
            'code' => 'real-code',
        ]);
    }

    public function test_production_wechat_login_rejects_direct_openid_when_mock_is_disabled(): void
    {
        config([
            'app.env' => 'production',
            'wechat.mini_program.mock' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(AuthBusiness::class)->wechatLogin([
            'openid' => 'forged-openid',
        ]);
    }

    public function test_send_code_dispatches_configured_webhook(): void
    {
        Http::fake([
            'https://notify.example.com/code' => Http::response(['success' => true]),
        ]);
        config([
            'verification.webhook_url' => 'https://notify.example.com/code',
            'verification.webhook_token' => 'secret-token',
        ]);

        $sent = app(AuthBusiness::class)->sendCode(['account' => 'notify@example.com', 'scene' => 'login']);

        $this->assertNotEmpty($sent['debug_code']);
        Http::assertSent(function ($request) use ($sent) {
            return $request->url() === 'https://notify.example.com/code'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['account'] === 'notify@example.com'
                && $request['scene'] === 'login'
                && $request['code'] === $sent['debug_code']
                && $request['expire_seconds'] === 300;
        });
    }

    public function test_production_send_code_requires_delivery_channel(): void
    {
        config([
            'app.env' => 'production',
            'verification.webhook_url' => '',
            'verification.mail.enabled' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(AuthBusiness::class)->sendCode(['account' => 'prod@example.com', 'scene' => 'login']);
    }

    public function test_production_send_code_can_dispatch_email_by_smtp(): void
    {
        config([
            'app.env' => 'production',
            'verification.webhook_url' => '',
            'verification.mail.enabled' => true,
            'verification.mail.host' => 'smtp.exmail.qq.com',
            'verification.mail.port' => 465,
            'verification.mail.username' => 'noreply@example.com',
            'verification.mail.password' => 'smtp-secret',
            'verification.mail.from_address' => 'noreply@example.com',
            'verification.mail.from_name' => '守护者max',
        ]);

        $business = \Mockery::mock(AuthBusiness::class, [
            app(CommonUserDao::class),
            app(AuthIdentityDao::class),
            app(AuthTokenDao::class),
            app(VerificationCodeDao::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $business->shouldReceive('sendSmtpMail')
            ->once()
            ->withArgs(function ($host, $port, $to, $subject, $body, $fromAddress, $fromName, $username, $password) {
                return $host === 'smtp.exmail.qq.com'
                    && $port === 465
                    && $to === 'prod@example.com'
                    && $subject === '守护者max 登录验证码'
                    && str_contains($body, '验证码')
                    && $fromAddress === 'noreply@example.com'
                    && $fromName === '守护者max'
                    && $username === 'noreply@example.com'
                    && $password === 'smtp-secret';
            });

        $sent = $business->sendCode(['account' => 'prod@example.com', 'scene' => 'login']);

        $this->assertSame('', $sent['debug_code']);
        $this->assertSame(1, VerificationCode::where('account', 'prod@example.com')->count());
    }

    public function test_send_code_does_not_persist_pending_code_when_delivery_fails(): void
    {
        Http::fake([
            'https://notify.example.com/code' => Http::response(['message' => 'provider down'], 502),
        ]);
        config([
            'verification.webhook_url' => 'https://notify.example.com/code',
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(AuthBusiness::class)->sendCode(['account' => 'failed@example.com', 'scene' => 'login']);
        } finally {
            $this->assertSame(0, VerificationCode::where('account', 'failed@example.com')->count());
        }
    }
}
