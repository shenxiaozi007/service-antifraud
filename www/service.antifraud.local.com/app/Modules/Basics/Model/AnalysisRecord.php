<?php

namespace App\Modules\Basics\Model;

use App\Kernel\Base\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalysisRecord extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'risk_level',
        'risk_score',
        'summary',
        'suggestions',
        'status',
        'error_message',
        'retry_count',
        'llm_model',
        'llm_duration_ms',
        'llm_raw_output',
        'cost_points',
        'frozen_points',
        'image_count',
        'duration_seconds',
        'analyzed_at',
    ];

    protected $casts = [
        'suggestions' => 'array',
        'llm_raw_output' => 'array',
        'analyzed_at' => 'datetime',
    ];

    /**
     * 关联风险项列表。
     *
     * @return HasMany
     */
    public function riskItems(): HasMany
    {
        return $this->hasMany(RiskItem::class, 'record_id');
    }

    /**
     * 关联上传文件列表。
     *
     * @return HasMany
     */
    public function fileAssets(): HasMany
    {
        return $this->hasMany(FileAsset::class, 'record_id');
    }

    /**
     * 关联提交分析的用户。
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 按用户筛选分析记录。
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
     * 按分析类型筛选：text/image/audio 等。
     *
     * @param Builder $builder 查询构造器
     * @param string $type 分析类型
     * @return Builder
     */
    public function scopeTypeQuery(Builder $builder, string $type): Builder
    {
        return $builder->where('type', $type);
    }

    /**
     * 按风险等级筛选。
     *
     * @param Builder $builder 查询构造器
     * @param string $riskLevel 风险等级
     * @return Builder
     */
    public function scopeRiskLevelQuery(Builder $builder, string $riskLevel): Builder
    {
        return $builder->where('risk_level', $riskLevel);
    }

    /**
     * 按分析状态筛选。
     *
     * @param Builder $builder 查询构造器
     * @param string $status 分析状态
     * @return Builder
     */
    public function scopeStatusQuery(Builder $builder, string $status): Builder
    {
        return $builder->where('status', $status);
    }
}
