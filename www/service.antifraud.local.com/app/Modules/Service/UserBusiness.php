<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Dao\UserDao;
use Illuminate\Http\Request;

class UserBusiness extends BaseBusiness
{
    public function __construct(protected UserDao $userDao)
    {
    }

    public function currentUser(Request $request)
    {
        $token = $this->bearerToken($request);
        if ($token === '') {
            $this->fail(401, '请先登录');
        }

        $user = $this->userDao->findByToken($token);
        if (!$user) {
            $this->fail(401, '登录已失效');
        }

        return $user;
    }

    public function me(Request $request): array
    {
        $user = $this->currentUser($request);

        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            'points_balance' => $user->points_balance,
        ];
    }
}
