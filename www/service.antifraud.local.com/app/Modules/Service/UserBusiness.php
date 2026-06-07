<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Libraries\CommonService\CommonServiceClient;
use App\Modules\Basics\Constant\PointConstant;
use App\Modules\Basics\Dao\UserDao;
use Illuminate\Http\Request;

class UserBusiness extends BaseBusiness
{
    public function __construct(protected UserDao $userDao, protected CommonServiceClient $commonServiceClient)
    {
    }

    public function currentUser(Request $request)
    {
        $token = $this->bearerToken($request);
        if ($token === '') {
            $this->fail(401, '请先登录');
        }

        $introspection = $this->commonServiceClient->introspect($token);
        if (!($introspection['active'] ?? false)) {
            $this->fail(401, '登录已失效');
        }

        $globalUser = $introspection['user'];
        $user = $this->userDao->findByGlobalUserId((int) $globalUser['id']);
        if (!$user) {
            $user = $this->userDao->create([
                'global_user_id' => (int) $globalUser['id'],
                'project_code' => config('common_service.project_code', 'antifraud'),
                'openid' => 'global_'.$globalUser['id'],
                'nickname' => $globalUser['nickname'] ?? '',
                'avatar_url' => $globalUser['avatar_url'] ?? '',
                'points_balance' => 0,
                'status' => (int) ($globalUser['status'] ?? 1),
                'api_token' => $token,
                'last_login_at' => now(),
            ]);
            $this->commonServiceClient->reward(
                (int) $globalUser['id'],
                PointConstant::NEW_USER_GIFT_POINTS,
                'new_user_'.config('common_service.project_code', 'antifraud').'_'.(int) $globalUser['id'],
                PointConstant::TYPE_GIFT,
                '新用户注册赠送'
            );
        }

        return $user;
    }

    public function me(Request $request): array
    {
        $user = $this->currentUser($request);
        $wallet = $this->commonServiceClient->balance($this->bearerToken($request));

        return [
            'id' => $user->id,
            'global_user_id' => $user->global_user_id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            'points_balance' => $wallet['balance'] ?? 0,
            'frozen_points' => $wallet['frozen_balance'] ?? 0,
        ];
    }
}
