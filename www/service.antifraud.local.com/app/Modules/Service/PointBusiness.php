<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Libraries\CommonService\CommonServiceClient;
use App\Modules\Basics\Constant\PointConstant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PointBusiness extends BaseBusiness
{
    public function __construct(
        protected UserBusiness $userBusiness,
        protected CommonServiceClient $commonServiceClient
    ) {
    }

    public function transactions(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->commonServiceClient->transactions($this->bearerToken($request), [
            'page_size' => (int) ($data['page_size'] ?? 20),
        ]);
    }

    /**
     * 用户观看激励视频广告后领取积分。
     *
     * @param Request $request HTTP 请求，包含广告平台幂等键等参数
     * @return array 公共广告奖励结果与最新钱包余额
     */
    public function adReward(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'idempotency_key' => 'required|string|max:128',
            'scene' => 'nullable|string|max:64',
            'platform' => 'nullable|string|max:64',
            'ad_unit_id' => 'nullable|string|max:128',
        ]);

        return $this->commonServiceClient->adReward((int) $user->global_user_id, [
            'idempotency_key' => $data['idempotency_key'],
            'scene' => $data['scene'] ?? 'daily_points',
            'platform' => $data['platform'] ?? 'wechat',
            'ad_unit_id' => $data['ad_unit_id'] ?? '',
            'reward_points' => PointConstant::AD_REWARD_POINTS,
            'daily_limit' => PointConstant::AD_REWARD_DAILY_LIMIT,
            'remark' => '观看广告奖励',
        ]);
    }

    /**
     * 用户每日签到领取积分，连续 7 天额外奖励一次。
     *
     * @param Request $request HTTP 请求
     * @return array 签到奖励结果与钱包余额
     */
    public function checkIn(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $globalUserId = (int) $user->global_user_id;
        $today = Carbon::today();
        $todayRelatedNo = $this->checkInRelatedNo($globalUserId, $today);
        $transactions = $this->commonServiceClient->transactionsByUser($globalUserId, ['page_size' => 100]);

        if ($this->hasTransaction($transactions['items'] ?? [], $todayRelatedNo, PointConstant::TYPE_CHECK_IN)) {
            $wallet = $this->commonServiceClient->balance($this->bearerToken($request));

            return [
                'checked_in' => false,
                'reward_points' => 0,
                'bonus_points' => 0,
                'continuous_days' => $this->continuousDays($transactions['items'] ?? [], $globalUserId, $today),
                'wallet' => $wallet,
            ];
        }

        $wallet = $this->commonServiceClient->reward(
            $globalUserId,
            PointConstant::CHECK_IN_POINTS,
            $todayRelatedNo,
            PointConstant::TYPE_CHECK_IN,
            '每日签到奖励'
        );

        $items = array_merge([[
            'related_no' => $todayRelatedNo,
            'type' => PointConstant::TYPE_CHECK_IN,
        ]], $transactions['items'] ?? []);
        $continuousDays = $this->continuousDays($items, $globalUserId, $today);
        $bonusPoints = 0;

        if ($continuousDays > 0 && $continuousDays % 7 === 0) {
            $bonusRelatedNo = $this->continuousCheckInRelatedNo($globalUserId, $today);
            if (!$this->hasTransaction($transactions['items'] ?? [], $bonusRelatedNo, PointConstant::TYPE_CONTINUOUS_CHECK_IN)) {
                $wallet = $this->commonServiceClient->reward(
                    $globalUserId,
                    PointConstant::CONTINUOUS_SEVEN_DAY_CHECK_IN_POINTS,
                    $bonusRelatedNo,
                    PointConstant::TYPE_CONTINUOUS_CHECK_IN,
                    '连续7天签到奖励'
                );
                $bonusPoints = PointConstant::CONTINUOUS_SEVEN_DAY_CHECK_IN_POINTS;
            }
        }

        return [
            'checked_in' => true,
            'reward_points' => PointConstant::CHECK_IN_POINTS,
            'bonus_points' => $bonusPoints,
            'continuous_days' => $continuousDays,
            'wallet' => $wallet,
        ];
    }

    /**
     * 判断流水列表是否包含指定幂等流水。
     *
     * @param array $items 钱包流水列表
     * @param string $relatedNo 关联业务编号
     * @param string $type 流水类型
     * @return bool
     */
    protected function hasTransaction(array $items, string $relatedNo, string $type): bool
    {
        foreach ($items as $item) {
            if (($item['related_no'] ?? '') === $relatedNo && ($item['type'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算截至指定日期的连续签到天数。
     *
     * @param array $items 钱包流水列表
     * @param int $globalUserId 公共用户 ID
     * @param Carbon $date 统计截止日期
     * @return int
     */
    protected function continuousDays(array $items, int $globalUserId, Carbon $date): int
    {
        $relatedNos = array_flip(array_map(fn ($item) => (string) ($item['related_no'] ?? ''), $items));
        $days = 0;

        for ($cursor = $date->copy(); $days < 100; $cursor->subDay()) {
            if (!isset($relatedNos[$this->checkInRelatedNo($globalUserId, $cursor)])) {
                break;
            }

            $days++;
        }

        return $days;
    }

    /**
     * 生成每日签到幂等编号。
     *
     * @param int $globalUserId 公共用户 ID
     * @param Carbon $date 签到日期
     * @return string
     */
    protected function checkInRelatedNo(int $globalUserId, Carbon $date): string
    {
        return 'checkin_'.config('common_service.project_code', 'antifraud').'_'.$globalUserId.'_'.$date->format('Ymd');
    }

    /**
     * 生成连续签到奖励幂等编号。
     *
     * @param int $globalUserId 公共用户 ID
     * @param Carbon $date 奖励日期
     * @return string
     */
    protected function continuousCheckInRelatedNo(int $globalUserId, Carbon $date): string
    {
        return 'checkin7_'.config('common_service.project_code', 'antifraud').'_'.$globalUserId.'_'.$date->format('Ymd');
    }
}
