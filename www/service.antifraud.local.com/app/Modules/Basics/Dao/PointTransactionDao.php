<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\PointTransaction;

class PointTransactionDao extends BaseDao
{
    public function __construct(PointTransaction $model)
    {
        parent::__construct($model);
    }

    public function userPage(int $userId, int $pageSize = 20)
    {
        return $this->query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->paginate($pageSize);
    }

    public function adminPage(array $filters, int $pageSize = 20)
    {
        return $this->query()
            ->when($filters['user_id'] ?? 0, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['type'] ?? '', fn ($query, string $type) => $query->where('type', $type))
            ->orderByDesc('id')
            ->paginate($pageSize);
    }
}
