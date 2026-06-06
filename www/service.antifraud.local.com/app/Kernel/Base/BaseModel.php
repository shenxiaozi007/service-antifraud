<?php

namespace App\Kernel\Base;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 业务模型基类。
 *
 * 当前项目暂时只沉淀 CRUD 必需的通用能力：表名读取和常用 scope。
 * 参考 wg-manage-service 的分层习惯后，具体查询条件统一放在 Model scope，
 * Dao 只负责按参数调用 scope，避免业务层到处手写 where 条件。
 */
abstract class BaseModel extends Model
{
    /**
     * 按主键倒序排序。
     *
     * 列表接口默认使用最新数据优先展示；如果后续业务有明确排序字段，
     * 可在具体 Model 内补充 scopeSortByXxxQuery。
     *
     * @param Builder $builder 查询构造器
     * @return Builder
     */
    public function scopeSortByIdDescQuery(Builder $builder): Builder
    {
        return $builder->orderByDesc($this->getKeyName());
    }

    /**
     * 按主键正序排序。
     *
     * @param Builder $builder 查询构造器
     * @return Builder
     */
    public function scopeSortByIdAscQuery(Builder $builder): Builder
    {
        return $builder->orderBy($this->getKeyName());
    }
}
