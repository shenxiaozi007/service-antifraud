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

    /**
     * 使用邮箱或手机号注册密码账号，并返回登录态。
     *
     * @param array $params 注册参数，包含 account/password/password_confirmation/nickname
     * @return array{token:string,user:array,is_new_user:bool}
     */
    public function passwordRegister(array $params): array
    {
        $data = validator($params, [
            'account' => 'required|string|max:191',
            'password' => 'required|string|min:8|max:64|confirmed',
            'nickname' => 'nullable|string|max:128',
        ], [], [
            'account' => '邮箱或手机号',
            'password' => '密码',
            'nickname' => '昵称',
        ])->validate();

        $identityType = $this->identityTypeByAccount($data['account']);
        $this->assertPasswordStrength($data['password']);

        return DB::transaction(function () use ($data, $identityType) {
            $identity = $this->identityDao->findIdentity($identityType, $data['account']);
            if ($identity && (string) $identity->password_hash !== '') {
                throw ValidationException::withMessages(['account' => '账号已注册，请直接登录']);
            }

            if ($identity) {
                $user = $this->userDao->find($identity->user_id);
                if (!$user || (int) $user->status !== 1) {
                    throw ValidationException::withMessages(['account' => '账号已禁用或不存在']);
                }

                $identity->fill([
                    'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                    'password_updated_at' => Carbon::now(),
                ])->save();
                $user->fill([
                    'nickname' => $data['nickname'] ?? $user->nickname,
                    'last_login_at' => Carbon::now(),
                ])->save();
                $user->is_new_user = false;

                return $this->loginResponse($user, 'password');
            }

            $user = $this->resolveUser($identityType, $data['account'], [
                'nickname' => $data['nickname'] ?? $this->defaultNickname($data['account']),
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'password_updated_at' => Carbon::now(),
            ]);

            return $this->loginResponse($user, 'password');
        });
    }

    /**
     * 使用邮箱或手机号和密码登录。
     *
     * @param array $params 登录参数，包含 account/password
     * @return array{token:string,user:array,is_new_user:bool}
     */
    public function passwordLogin(array $params): array
    {
        $data = validator($params, [
            'account' => 'required|string|max:191',
            'password' => 'required|string|max:64',
        ], [], [
            'account' => '邮箱或手机号',
            'password' => '密码',
        ])->validate();

        $identityType = $this->identityTypeByAccount($data['account']);
        $identity = $this->identityDao->findIdentity($identityType, $data['account']);
        if (!$identity || !password_verify($data['password'], (string) $identity->password_hash)) {
            throw ValidationException::withMessages(['account' => '账号或密码错误']);
        }

        $user = $this->userDao->find($identity->user_id);
        if (!$user || (int) $user->status !== 1) {
            throw ValidationException::withMessages(['account' => '账号已禁用或不存在']);
        }

        $user->fill(['last_login_at' => Carbon::now()])->save();

        return $this->loginResponse($user, 'password');
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
            $identityExtra = $profile;
            unset($identityExtra['password_hash'], $identityExtra['password_updated_at']);

            $this->identityDao->store([
                'user_id' => $user->id,
                'identity_type' => $type,
                'identifier' => $identifier,
                'unionid' => $profile['unionid'] ?? '',
                'extra' => $identityExtra,
                'password_hash' => $profile['password_hash'] ?? '',
                'password_updated_at' => $profile['password_updated_at'] ?? null,
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

    /**
     * 根据账号格式判断身份类型，仅允许邮箱或手机号注册登录。
     *
     * @param string $account 用户输入的邮箱或手机号
     * @return string email 或 mobile
     */
    protected function identityTypeByAccount(string $account): string
    {
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        if (preg_match('/^1[3-9]\d{9}$/', $account) === 1) {
            return 'mobile';
        }

        throw ValidationException::withMessages(['account' => '请输入正确的邮箱或手机号']);
    }

    /**
     * 校验密码复杂度，避免注册弱密码账号。
     *
     * @param string $password 用户输入的明文密码
     * @return void
     */
    protected function assertPasswordStrength(string $password): void
    {
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw ValidationException::withMessages(['password' => '密码需至少包含字母和数字']);
        }
    }

    protected function dispatchVerificationCode(string $account, string $scene, string $code): void
    {
        $webhookUrl = (string) config('verification.webhook_url', '');
        if ($webhookUrl === '') {
            if ($this->shouldSendVerificationCodeByMail($account)) {
                $this->sendVerificationCodeByMail($account, $scene, $code);

                return;
            }

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

    protected function shouldSendVerificationCodeByMail(string $account): bool
    {
        return filter_var($account, FILTER_VALIDATE_EMAIL)
            && (bool) config('verification.mail.enabled', false);
    }

    protected function sendVerificationCodeByMail(string $account, string $scene, string $code): void
    {
        $host = (string) config('verification.mail.host', '');
        $port = (int) config('verification.mail.port', 465);
        $username = (string) config('verification.mail.username', '');
        $password = (string) config('verification.mail.password', '');
        $fromAddress = (string) config('verification.mail.from_address', $username);
        $fromName = (string) config('verification.mail.from_name', '守护者max');
        if ($host === '' || $username === '' || $password === '' || $fromAddress === '') {
            throw ValidationException::withMessages(['account' => '邮箱验证码发送配置缺失']);
        }

        $subject = '守护者max 登录验证码';
        $sceneText = $scene === 'login' ? '登录/注册' : $scene;
        $body = "您的{$sceneText}验证码是：{$code}\r\n\r\n验证码 5 分钟内有效。如非本人操作，请忽略本邮件。";

        try {
            $this->sendSmtpMail($host, $port, $account, $subject, $body, $fromAddress, $fromName, $username, $password);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['account' => '邮箱验证码发送失败：'.$exception->getMessage()]);
        }
    }

    protected function sendSmtpMail(
        string $host,
        int $port,
        string $to,
        string $subject,
        string $body,
        string $fromAddress,
        string $fromName,
        string $username,
        string $password,
    ): void {
        $timeout = (int) config('verification.mail.timeout', 15);
        $remote = ((string) config('verification.mail.encryption', 'ssl') === 'ssl' ? 'ssl://' : '').$host.':'.$port;
        $socket = stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new \RuntimeException($errstr ?: 'SMTP 连接失败');
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->smtpExpect($socket, [220]);
            $serverName = parse_url((string) config('app.url', 'localhost'), PHP_URL_HOST) ?: 'localhost';
            $this->smtpCommand($socket, 'EHLO '.$serverName, [250]);
            $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
            $this->smtpCommand($socket, base64_encode($username), [334]);
            $this->smtpCommand($socket, base64_encode($password), [235]);
            $this->smtpCommand($socket, 'MAIL FROM:<'.$fromAddress.'>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<'.$to.'>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);
            fwrite($socket, $this->smtpMessage($to, $subject, $body, $fromAddress, $fromName));
            $this->smtpExpect($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    protected function smtpMessage(string $to, string $subject, string $body, string $fromAddress, string $fromName): string
    {
        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $encodedFromName = '=?UTF-8?B?'.base64_encode($fromName).'?=';

        return implode("\r\n", [
            'Date: '.date(DATE_RFC2822),
            'From: '.$encodedFromName.' <'.$fromAddress.'>',
            'To: <'.$to.'>',
            'Subject: '.$encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($body)),
            '.',
            '',
        ]);
    }

    protected function smtpCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command."\r\n");

        return $this->smtpExpect($socket, $expectedCodes);
    }

    protected function smtpExpect($socket, array $expectedCodes): string
    {
        $response = '';
        do {
            $line = fgets($socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            $continued = isset($line[3]) && $line[3] === '-';
        } while ($continued);

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException(trim($response) ?: 'SMTP 响应异常');
        }

        return $response;
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
