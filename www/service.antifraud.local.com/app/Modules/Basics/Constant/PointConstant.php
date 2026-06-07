<?php

namespace App\Modules\Basics\Constant;

class PointConstant
{
    public const NEW_USER_GIFT_POINTS = 500;
    public const AD_REWARD_POINTS = 10;
    public const AD_REWARD_DAILY_LIMIT = 5;
    public const CHECK_IN_POINTS = 10;
    public const CONTINUOUS_SEVEN_DAY_CHECK_IN_POINTS = 20;
    public const IMAGE_ANALYSIS_POINTS = 20;
    public const AUDIO_ANALYSIS_POINTS_PER_MINUTE = 10;

    public const TYPE_GIFT = 'gift';
    public const TYPE_AD_REWARD = 'ad_reward';
    public const TYPE_CHECK_IN = 'check_in';
    public const TYPE_CONTINUOUS_CHECK_IN = 'continuous_check_in';
    public const TYPE_ANALYSIS_COST = 'analysis_cost';
    public const TYPE_RECHARGE = 'recharge';
    public const TYPE_REFUND = 'refund';
}
