<?php

namespace Tests;

use App\Modules\Basics\Dao\Ad\AdRewardRecordDao;
use App\Modules\Basics\Model\Ad\AdRewardRecord;
use App\Modules\Service\Business\Ad\AdRewardBusiness;
use App\Modules\Service\Business\Wallet\WalletBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdRewardBusinessTest extends TestCase
{
    public function test_ad_reward_records_reward_and_wallet_balance(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $records = new InMemoryAdRewardRecordDao();
        $wallet = new InMemoryRewardWalletBusiness();
        $business = new AdRewardBusiness($records, $wallet);

        $result = $business->reward([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'scene' => 'daily_points',
            'platform' => 'wechat',
            'ad_unit_id' => 'ad_unit_1',
            'idempotency_key' => 'reward_event_1',
            'reward_points' => 10,
            'daily_limit' => 5,
        ]);

        $this->assertTrue($result['rewarded']);
        $this->assertSame(10, $result['wallet']['balance']);
        $this->assertSame(1, $result['daily_count']);
        $this->assertSame('ad_reward', $wallet->rewards[0]['type']);
        $this->assertSame(1, count($records->items));
    }

    public function test_ad_reward_idempotency_does_not_reward_twice(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $records = new InMemoryAdRewardRecordDao();
        $wallet = new InMemoryRewardWalletBusiness();
        $business = new AdRewardBusiness($records, $wallet);

        $params = [
            'user_id' => 1,
            'project_code' => 'antifraud',
            'idempotency_key' => 'same_event',
            'reward_points' => 10,
            'daily_limit' => 5,
        ];

        $business->reward($params);
        $duplicate = $business->reward($params);

        $this->assertFalse($duplicate['rewarded']);
        $this->assertSame(10, $duplicate['wallet']['balance']);
        $this->assertCount(1, $wallet->rewards);
        $this->assertCount(1, $records->items);
    }

    public function test_ad_reward_rejects_when_daily_limit_is_used(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $records = new InMemoryAdRewardRecordDao();
        $wallet = new InMemoryRewardWalletBusiness();
        $business = new AdRewardBusiness($records, $wallet);

        $business->reward([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'idempotency_key' => 'first_event',
            'daily_limit' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $business->reward([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'idempotency_key' => 'second_event',
            'daily_limit' => 1,
        ]);
    }
}

class InMemoryAdRewardRecordDao extends AdRewardRecordDao
{
    public array $items = [];

    public function __construct()
    {
    }

    public function findByIdempotencyKey(string $projectCode, int $userId, string $idempotencyKey): ?AdRewardRecord
    {
        foreach ($this->items as $item) {
            if ($item->project_code === $projectCode
                && (int) $item->user_id === $userId
                && $item->idempotency_key === $idempotencyKey) {
                return $item;
            }
        }

        return null;
    }

    public function countDailyRewards(string $projectCode, int $userId, string $scene, int $rewardDate): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            if ($item->project_code === $projectCode
                && (int) $item->user_id === $userId
                && $item->scene === $scene
                && (int) $item->reward_date === $rewardDate
                && $item->status === 'completed') {
                $count++;
            }
        }

        return $count;
    }

    public function store(array $params, array $extra = []): Model
    {
        $record = new AdRewardRecord(array_merge($params, $extra));
        $record->exists = true;
        $this->items[] = $record;

        return $record;
    }
}

class InMemoryRewardWalletBusiness extends WalletBusiness
{
    public array $rewards = [];
    private int $balance = 0;

    public function __construct()
    {
    }

    public function reward(array $params): array
    {
        $this->rewards[] = $params;
        $this->balance += (int) $params['amount'];

        return $this->balance((int) $params['user_id'], $params['project_code']);
    }

    public function balance(int $userId, string $projectCode): array
    {
        return [
            'user_id' => $userId,
            'project_code' => $projectCode,
            'balance' => $this->balance,
            'frozen_balance' => 0,
        ];
    }
}
