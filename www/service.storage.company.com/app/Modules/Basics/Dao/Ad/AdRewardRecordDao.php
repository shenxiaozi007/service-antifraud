<?php

namespace App\Modules\Basics\Dao\Ad;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Ad\AdRewardRecord;
use Illuminate\Database\Eloquent\Model;

class AdRewardRecordDao extends BaseDao
{
    public function __construct(protected AdRewardRecord $adRewardRecord)
    {
    }

    protected function getModel(): Model
    {
        return $this->adRewardRecord;
    }

    /**
     * 根据项目、用户和幂等键查询广告奖励记录。
     *
     * @param string $projectCode 项目编码
     * @param int $userId 用户 ID
     * @param string $idempotencyKey 广告奖励幂等键
     * @return AdRewardRecord|null
     */
    public function findByIdempotencyKey(string $projectCode, int $userId, string $idempotencyKey): ?AdRewardRecord
    {
        return $this->newBuilder()
            ->where('project_code', $projectCode)
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * 统计用户指定日期的广告奖励次数。
     *
     * @param string $projectCode 项目编码
     * @param int $userId 用户 ID
     * @param string $scene 广告场景
     * @param int $rewardDate 奖励日期，格式 Ymd
     * @return int
     */
    public function countDailyRewards(string $projectCode, int $userId, string $scene, int $rewardDate): int
    {
        return $this->newBuilder()
            ->where('project_code', $projectCode)
            ->where('user_id', $userId)
            ->where('scene', $scene)
            ->where('reward_date', $rewardDate)
            ->where('status', 'completed')
            ->count();
    }
}
