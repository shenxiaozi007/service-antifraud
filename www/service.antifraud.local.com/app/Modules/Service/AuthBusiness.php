<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Libraries\CommonService\CommonServiceClient;
use App\Modules\Basics\Dao\UserDao;
use Carbon\Carbon;

class AuthBusiness extends BaseBusiness
{
    public function __construct(
        protected CommonServiceClient $commonServiceClient,
        protected UserDao $userDao
    ) {
    }

    public function wechatLogin(array $params): array
    {
        $login = $this->commonServiceClient->wechatLogin($params);
        $user = $this->syncLocalUser($login['user'], $login['token']);

        return [
            'token' => $login['token'],
            'user' => $this->formatUser($user),
            'is_new_user' => (bool) ($login['is_new_user'] ?? false),
        ];
    }

    public function sendCode(array $params): array
    {
        return $this->commonServiceClient->sendCode($params);
    }

    public function codeLogin(array $params): array
    {
        $login = $this->commonServiceClient->codeLogin($params);
        $user = $this->syncLocalUser($login['user'], $login['token']);

        return [
            'token' => $login['token'],
            'user' => $this->formatUser($user),
            'is_new_user' => (bool) ($login['is_new_user'] ?? false),
        ];
    }

    public function syncLocalUser(array $globalUser, string $token)
    {
        $user = $this->userDao->findByGlobalUserId((int) $globalUser['id']);
        $data = [
            'global_user_id' => (int) $globalUser['id'],
            'project_code' => config('common_service.project_code', 'antifraud'),
            'openid' => 'global_'.$globalUser['id'],
            'nickname' => $globalUser['nickname'] ?? '',
            'avatar_url' => $globalUser['avatar_url'] ?? '',
            'status' => (int) ($globalUser['status'] ?? 1),
            'api_token' => $token,
            'last_login_at' => Carbon::now(),
        ];

        if (!$user) {
            $data['points_balance'] = 0;
            return $this->userDao->create($data);
        }

        $user->fill($data)->save();

        return $user;
    }

    public function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'global_user_id' => $user->global_user_id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            'points_balance' => $user->points_balance,
            'status' => $user->status,
        ];
    }
}
