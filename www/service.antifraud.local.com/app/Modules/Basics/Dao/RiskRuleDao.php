<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\RiskRule;

class RiskRuleDao extends BaseDao
{
    public function __construct(RiskRule $model)
    {
        parent::__construct($model);
    }

    public function enabledRules()
    {
        return $this->query()->where('enabled', 1)->orderByDesc('weight')->get();
    }

    public function page(array $filters, int $pageSize = 20)
    {
        return $this->query()
            ->when($filters['category'] ?? '', fn ($query, string $category) => $query->where('category', $category))
            ->when(isset($filters['enabled']) && $filters['enabled'] !== '', fn ($query) => $query->where('enabled', (int) $filters['enabled']))
            ->orderByDesc('id')
            ->paginate($pageSize);
    }
}
