<?php

namespace App\Modules\Basics\Model;

use App\Kernel\Base\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class PointTransaction extends BaseModel
{
    protected $fillable = [
        'user_id',
        'related_record_id',
        'amount',
        'balance_after',
        'type',
        'status',
        'remark',
    ];

    /**
     * 按用户筛选积分流水。
     *
     * @param Builder $builder 查询构造器
     * @param int $userId 用户 ID
     * @return Builder
     */
    public function scopeUserIdQuery(Builder $builder, int $userId): Builder
    {
        return $builder->where('user_id', $userId);
    }

    /**
     * 按流水类型筛选。
     *
     * @param Builder $builder 查询构造器
     * @param string $type 流水类型
     * @return Builder
     */
    public function scopeTypeQuery(Builder $builder, string $type): Builder
    {
        return $builder->where('type', $type);
    }
}
