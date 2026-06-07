<?php

namespace App\Modules\Basics\Model\Ad;

use Illuminate\Database\Eloquent\Model;

class AdRewardRecord extends Model
{
    protected $fillable = [
        'reward_no',
        'user_id',
        'project_code',
        'scene',
        'platform',
        'ad_unit_id',
        'idempotency_key',
        'reward_points',
        'reward_date',
        'wallet_related_no',
        'status',
        'remark',
    ];
}
