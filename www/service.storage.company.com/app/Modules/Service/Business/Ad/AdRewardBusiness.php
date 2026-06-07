<?php

namespace App\Modules\Service\Business\Ad;

use App\Modules\Basics\Dao\Ad\AdRewardRecordDao;
use App\Modules\Service\Business\Wallet\WalletBusiness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdRewardBusiness
{
    public function __construct(
        protected AdRewardRecordDao $recordDao,
        protected WalletBusiness $walletBusiness,
    ) {
    }

    /**
     * 发放广告奖励积分，并记录广告奖励完成明细。
     *
     * @param array $params 奖励参数：user_id/project_code/idempotency_key/reward_points/daily_limit 等
     * @return array{rewarded:bool,daily_count:int,daily_limit:int,wallet:array,record:array}
     */
    public function reward(array $params): array
    {
        $data = validator($params, [
            'user_id' => 'required|integer|min:1',
            'project_code' => 'required|string|max:64',
            'scene' => 'nullable|string|max:64',
            'platform' => 'nullable|string|max:64',
            'ad_unit_id' => 'nullable|string|max:128',
            'idempotency_key' => 'required|string|max:128',
            'reward_points' => 'nullable|integer|min:1|max:100000',
            'daily_limit' => 'nullable|integer|min:1|max:1000',
            'remark' => 'nullable|string|max:255',
        ])->validate();

        $data['scene'] = $data['scene'] ?? 'rewarded_video';
        $data['platform'] = $data['platform'] ?? '';
        $data['ad_unit_id'] = $data['ad_unit_id'] ?? '';
        $data['reward_points'] = (int) ($data['reward_points'] ?? 10);
        $data['daily_limit'] = (int) ($data['daily_limit'] ?? 1);
        $data['remark'] = $data['remark'] ?? '广告奖励';
        $rewardDate = (int) Carbon::now()->format('Ymd');

        return DB::transaction(function () use ($data, $rewardDate) {
            $record = $this->recordDao->findByIdempotencyKey(
                $data['project_code'],
                (int) $data['user_id'],
                $data['idempotency_key']
            );

            if ($record) {
                return $this->formatResult(false, $record, $data['daily_limit']);
            }

            $dailyCount = $this->recordDao->countDailyRewards(
                $data['project_code'],
                (int) $data['user_id'],
                $data['scene'],
                $rewardDate
            );
            if ($dailyCount >= $data['daily_limit']) {
                throw ValidationException::withMessages(['daily_limit' => '今日广告奖励次数已用完']);
            }

            $walletRelatedNo = 'ad_'.Str::lower(Str::random(24));
            $wallet = $this->walletBusiness->reward([
                'user_id' => $data['user_id'],
                'project_code' => $data['project_code'],
                'amount' => $data['reward_points'],
                'related_no' => $walletRelatedNo,
                'type' => 'ad_reward',
                'remark' => $data['remark'],
            ]);

            $record = $this->recordDao->store([
                'reward_no' => 'ar_'.Str::lower(Str::random(24)),
                'user_id' => $data['user_id'],
                'project_code' => $data['project_code'],
                'scene' => $data['scene'],
                'platform' => $data['platform'],
                'ad_unit_id' => $data['ad_unit_id'],
                'idempotency_key' => $data['idempotency_key'],
                'reward_points' => $data['reward_points'],
                'reward_date' => $rewardDate,
                'wallet_related_no' => $walletRelatedNo,
                'status' => 'completed',
                'remark' => $data['remark'],
            ]);

            return $this->formatResult(true, $record, $data['daily_limit'], $wallet, $dailyCount + 1);
        });
    }

    /**
     * 格式化广告奖励结果。
     *
     * @param mixed $record 广告奖励记录
     * @param int $dailyLimit 每日奖励次数上限
     * @param array|null $wallet 钱包余额
     * @param int|null $dailyCount 当日已奖励次数
     * @return array{rewarded:bool,daily_count:int,daily_limit:int,wallet:array,record:array}
     */
    protected function formatResult(bool $rewarded, $record, int $dailyLimit, ?array $wallet = null, ?int $dailyCount = null): array
    {
        $wallet = $wallet ?: $this->walletBusiness->balance((int) $record->user_id, (string) $record->project_code);

        return [
            'rewarded' => $rewarded,
            'daily_count' => $dailyCount ?? $this->recordDao->countDailyRewards(
                (string) $record->project_code,
                (int) $record->user_id,
                (string) $record->scene,
                (int) $record->reward_date
            ),
            'daily_limit' => $dailyLimit,
            'wallet' => $wallet,
            'record' => [
                'reward_no' => $record->reward_no,
                'user_id' => $record->user_id,
                'project_code' => $record->project_code,
                'scene' => $record->scene,
                'platform' => $record->platform,
                'ad_unit_id' => $record->ad_unit_id,
                'reward_points' => $record->reward_points,
                'reward_date' => $record->reward_date,
                'wallet_related_no' => $record->wallet_related_no,
                'status' => $record->status,
            ],
        ];
    }
}
