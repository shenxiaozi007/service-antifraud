<?php

namespace App\Modules\Basics\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalysisRecord extends Model
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
        'cost_points',
        'frozen_points',
        'image_count',
        'duration_seconds',
        'analyzed_at',
    ];

    protected $casts = [
        'suggestions' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function riskItems(): HasMany
    {
        return $this->hasMany(RiskItem::class, 'record_id');
    }

    public function fileAssets(): HasMany
    {
        return $this->hasMany(FileAsset::class, 'record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
