<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Constant\PointConstant;
use App\Modules\Basics\Dao\PointTransactionDao;
use App\Modules\Basics\Dao\UserDao;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuthBusiness extends BaseBusiness
{
    public function __construct(
        protected UserDao $userDao,
        protected PointTransactionDao $pointTransactionDao
    ) {
    }

    public function wechatLogin(array $params): array
    {
        $data = $this->validate($params, [
            'code' => 'required|string|max:128',
            'openid' => 'nullable|string|max:128',
            'unionid' => 'nullable|string|max:128',
            'nickname' => 'nullable|string|max:128',
            'avatar_url' => 'nullable|string|max:512',
        ]);

        $openid = $data['openid'] ?? ('mock_'.$data['code']);
        $user = $this->userDao->findByOpenid($openid);
        $isNew = false;

        if (!$user) {
            $isNew = true;
            $user = $this->userDao->create([
                'openid' => $openid,
                'unionid' => $data['unionid'] ?? null,
                'nickname' => $data['nickname'] ?? '微信用户',
                'avatar_url' => $data['avatar_url'] ?? null,
                'points_balance' => PointConstant::NEW_USER_GIFT_POINTS,
                'status' => 1,
                'api_token' => Str::random(64),
                'last_login_at' => Carbon::now(),
            ]);
        } else {
            $user->fill([
                'unionid' => $data['unionid'] ?? $user->unionid,
                'nickname' => $data['nickname'] ?? $user->nickname,
                'avatar_url' => $data['avatar_url'] ?? $user->avatar_url,
                'api_token' => $user->api_token ?: Str::random(64),
                'last_login_at' => Carbon::now(),
            ])->save();
        }

        if ($isNew) {
            $this->pointTransactionDao->create([
                'user_id' => $user->id,
                'related_record_id' => null,
                'amount' => PointConstant::NEW_USER_GIFT_POINTS,
                'balance_after' => $user->points_balance,
                'type' => PointConstant::TYPE_GIFT,
                'status' => 'completed',
                'remark' => '新用户赠送点数',
            ]);
        }

        return [
            'token' => $user->api_token,
            'user' => $this->formatUser($user),
        ];
    }

    public function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            'points_balance' => $user->points_balance,
            'status' => $user->status,
        ];
    }
}
