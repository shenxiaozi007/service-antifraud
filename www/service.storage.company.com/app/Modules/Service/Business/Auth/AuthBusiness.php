<?php

namespace App\Modules\Service\Business\Auth;

use App\Modules\Basics\Dao\Auth\AuthIdentityDao;
use App\Modules\Basics\Dao\Auth\AuthTokenDao;
use App\Modules\Basics\Dao\Auth\CommonUserDao;
use App\Modules\Basics\Dao\Auth\VerificationCodeDao;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthBusiness
{
    public function __construct(
        protected CommonUserDao $userDao,
        protected AuthIdentityDao $identityDao,
        protected AuthTokenDao $tokenDao,
        protected VerificationCodeDao $codeDao,
    ) {
    }

    public function sendCode(array $params): array
    {
        $data = validator($params, [
            'account' => 'required|string|max:191',
            'scene' => 'nullable|string|max:32',
        ])->validate();

        $scene = $data['scene'] ?? 'login';
        $latest = $this->codeDao->latestPending($data['account'], $scene);
        if ($latest && $latest->created_at && $latest->created_at->gt(Carbon::now()->subSeconds(60))) {
            throw ValidationException::withMessages(['account' => '验证码发送太频繁']);
        }

        $code = (string) random_int(100000, 999999);
        $this->dispatchVerificationCode($data['account'], $scene, $code);

        $this->codeDao->store([
            'account' => $data['account'],
            'scene' => $scene,
            'code' => $code,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        return [
            'expire_seconds' => 300,
            'debug_code' => config('app.env') === 'production' ? '' : $code,
        ];
    }

    public function codeLogin(array $params): array
    {
        $data = validator($params, [
            'account' => 'required|string|max:191',
            'code' => 'required|string|max:16',
            'scene' => 'nullable|string|max:32',
            'nickname' => 'nullable|string|max:128',
        ])->validate();

        $scene = $data['scene'] ?? 'login';
        $code = $this->codeDao->latestPending($data['account'], $scene);
        if (!$code || $code->code !== $data['code'] || $code->expires_at->lte(Carbon::now())) {
            throw ValidationException::withMessages(['code' => '验证码错误或已过期']);
        }

        $identityType = filter_var($data['account'], FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';

        return DB::transaction(function () use ($data, $code, $identityType) {
            $user = $this->resolveUser($identityType, $data['account'], [
                'nickname' => $data['nickname'] ?? $this->defaultNickname($data['account']),
            ]);

            $code->fill(['status' => 'used', 'used_at' => Carbon::now()])->save();

            return $this->loginResponse($user, 'code');
        });
    }

    public function wechatLogin(array $params): array
    {
        $data = validator($params, [
            'code' => 'required_without:openid|string|max:128',
            'openid' => 'nullable|string|max:128',
            'unionid' => 'nullable|string|max:128',
            'nickname' => 'nullable|string|max:128',
            'avatar_url' => 'nullable|string|max:512',
        ])->validate();

        $session = $this->wechatSession($data);
        $openid = $session['openid'];
        $unionid = $session['unionid'] ?? ($data['unionid'] ?? '');

        return DB::transaction(function () use ($data, $openid, $unionid, $session) {
            $user = $this->resolveUser('wechat', $openid, [
                'nickname' => $data['nickname'] ?? '微信用户',
                'avatar_url' => $data['avatar_url'] ?? '',
                'unionid' => $unionid,
                'session' => $session,
            ]);

            return $this->loginResponse($user, 'wechat');
        });
    }

    public function introspect(string $token): array
    {
        $authToken = $this->tokenDao->findValidToken($token);
        if (!$authToken) {
            return ['active' => false];
        }

        $user = $this->userDao->find($authToken->user_id);
        if (!$user || (int) $user->status !== 1) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'user' => $this->formatUser($user),
        ];
    }

    protected function resolveUser(string $type, string $identifier, array $profile)
    {
        $identity = $this->identityDao->findIdentity($type, $identifier);
        $isNew = false;
        $user = $identity ? $this->userDao->find($identity->user_id) : null;

        if (!$user) {
            $isNew = true;
            $user = $this->userDao->store([
                'nickname' => $profile['nickname'] ?? '',
                'avatar_url' => $profile['avatar_url'] ?? '',
                'status' => 1,
                'last_login_at' => Carbon::now(),
            ]);
        } else {
            $user->fill([
                'nickname' => $profile['nickname'] ?? $user->nickname,
                'avatar_url' => $profile['avatar_url'] ?? $user->avatar_url,
                'last_login_at' => Carbon::now(),
            ])->save();
        }

        if (!$identity) {
            $this->identityDao->store([
                'user_id' => $user->id,
                'identity_type' => $type,
                'identifier' => $identifier,
                'unionid' => $profile['unionid'] ?? '',
                'extra' => $profile,
            ]);
        }

        $user->is_new_user = $isNew;

        return $user;
    }

    protected function loginResponse($user, string $clientType): array
    {
        $token = Str::random(64);
        $this->tokenDao->store([
            'user_id' => $user->id,
            'token' => $token,
            'client_type' => $clientType,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        return [
            'token' => $token,
            'user' => $this->formatUser($user),
            'is_new_user' => (bool) ($user->is_new_user ?? false),
        ];
    }

    protected function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
        ];
    }

    protected function defaultNickname(string $account): string
    {
        return '用户'.substr(md5($account), 0, 6);
    }

    protected function dispatchVerificationCode(string $account, string $scene, string $code): void
    {
        $webhookUrl = (string) config('verification.webhook_url', '');
        if ($webhookUrl === '') {
            if (config('app.env') === 'production') {
                throw ValidationException::withMessages(['account' => '验证码发送通道未配置']);
            }

            return;
        }

        $payload = [
            'account' => $account,
            'scene' => $scene,
            'code' => $code,
            'expire_seconds' => 300,
        ];
        $http = Http::timeout((int) config('verification.timeout', 10))->acceptJson();
        $token = (string) config('verification.webhook_token', '');
        if ($token !== '') {
            $http = $http->withToken($token);
        }

        $response = $http->post($webhookUrl, $payload);
        if (!$response->successful()) {
            throw ValidationException::withMessages(['account' => '验证码发送失败：'.$response->body()]);
        }
    }

    protected function wechatSession(array $data): array
    {
        if (!empty($data['openid'])) {
            if (config('app.env') === 'production' && ! $this->shouldMockWechatLogin()) {
                throw ValidationException::withMessages(['openid' => '生产环境不允许直传 openid']);
            }

            return [
                'openid' => (string) $data['openid'],
                'unionid' => (string) ($data['unionid'] ?? ''),
            ];
        }

        if ($this->shouldMockWechatLogin()) {
            return [
                'openid' => 'mock_'.$data['code'],
                'unionid' => (string) ($data['unionid'] ?? ''),
            ];
        }

        if ((string) config('wechat.mini_program.app_id', '') === '' || (string) config('wechat.mini_program.app_secret', '') === '') {
            throw ValidationException::withMessages(['code' => '微信登录配置缺失']);
        }

        $response = Http::timeout(10)->get((string) config('wechat.mini_program.session_url'), [
            'appid' => config('wechat.mini_program.app_id'),
            'secret' => config('wechat.mini_program.app_secret'),
            'js_code' => $data['code'],
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw ValidationException::withMessages(['code' => '微信登录失败：'.$response->body()]);
        }

        $body = $response->json();
        if (!is_array($body) || empty($body['openid'])) {
            $message = is_array($body) ? ($body['errmsg'] ?? '微信未返回 openid') : '微信登录响应异常';
            throw ValidationException::withMessages(['code' => '微信登录失败：'.$message]);
        }

        return [
            'openid' => (string) $body['openid'],
            'unionid' => (string) ($body['unionid'] ?? ''),
            'session_key' => (string) ($body['session_key'] ?? ''),
        ];
    }

    protected function shouldMockWechatLogin(): bool
    {
        return (bool) config('wechat.mini_program.mock', false);
    }
}
