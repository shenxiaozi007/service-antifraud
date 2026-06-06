<?php

namespace App\Modules\Basics\Model;

use App\Kernel\Base\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class RiskRule extends BaseModel
{
    protected $fillable = [
        'category',
        'keyword',
        'severity',
        'weight',
        'enabled',
    ];

    /**
     * 按风险分类精确筛选。
     *
     * @param Builder $builder 查询构造器
     * @param string $category 风险分类
     * @return Builder
     */
    public function scopeCategoryQuery(Builder $builder, string $category): Builder
    {
        return $builder->where('category', $category);
    }

    /**
     * 按启用状态筛选。
     *
     * @param Builder $builder 查询构造器
     * @param int $enabled 启用状态：0 关闭，1 启用
     * @return Builder
     */
    public function scopeEnabledQuery(Builder $builder, int $enabled): Builder
    {
        return $builder->where('enabled', $enabled);
    }

    /**
     * 规则执行时按权重倒序读取，权重越高越先命中。
     *
     * @param Builder $builder 查询构造器
     * @return Builder
     */
    public function scopeSortByWeightDescQuery(Builder $builder): Builder
    {
        return $builder->orderByDesc('weight');
    }
}
