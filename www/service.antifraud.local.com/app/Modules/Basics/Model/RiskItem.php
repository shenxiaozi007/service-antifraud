<?php

namespace App\Modules\Basics\Model;

use App\Kernel\Base\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class RiskItem extends BaseModel
{
    protected $fillable = [
        'record_id',
        'category',
        'severity',
        'description',
        'evidence_text',
    ];

    /**
     * 按分析记录筛选风险项。
     *
     * @param Builder $builder 查询构造器
     * @param int $recordId 分析记录 ID
     * @return Builder
     */
    public function scopeRecordIdQuery(Builder $builder, int $recordId): Builder
    {
        return $builder->where('record_id', $recordId);
    }
}
