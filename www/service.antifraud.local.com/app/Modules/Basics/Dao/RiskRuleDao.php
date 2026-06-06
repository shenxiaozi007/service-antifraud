<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\RiskRule;
use Illuminate\Database\Eloquent\Collection;

class RiskRuleDao extends BaseDao
{
    /**
     * @param RiskRule $model 风险规则模型
     */
    public function __construct(RiskRule $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取启用中的风险规则。
     *
     * 规则检测需要优先命中高权重规则，所以这里固定按 weight 倒序。
     *
     * @return Collection
     */
    public function enabledRules()
    {
        return $this->getList([
            'enabled' => 1,
            'sort_by_weight_desc' => true,
        ]);
    }

    /**
     * 管理端风险规则分页列表。
     *
     * @param array $filters 查询条件：category、enabled、page_size
     * @param int $pageSize 每页条数
     * @return LengthAwarePaginator
     */
    public function page(array $filters, int $pageSize = 20)
    {
        return $this->getPageList(array_merge($filters, [
            'page_size' => $pageSize,
            'sort_by_id_desc' => true,
        ]));
    }
}
