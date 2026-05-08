<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\AnalysisRecord;

class AnalysisRecordDao extends BaseDao
{
    public function __construct(AnalysisRecord $model)
    {
        parent::__construct($model);
    }

    public function findUserRecord(int $recordId, int $userId): ?AnalysisRecord
    {
        return $this->query()
            ->with(['riskItems', 'fileAssets'])
            ->where('id', $recordId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findWithDetail(int $recordId): ?AnalysisRecord
    {
        return $this->query()->with(['riskItems', 'fileAssets', 'user'])->find($recordId);
    }

    public function userPage(int $userId, array $filters, int $pageSize = 20)
    {
        return $this->query()
            ->where('user_id', $userId)
            ->when($filters['type'] ?? '', fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['risk_level'] ?? '', fn ($query, string $level) => $query->where('risk_level', $level))
            ->orderByDesc('id')
            ->paginate($pageSize);
    }

    public function adminPage(array $filters, int $pageSize = 20)
    {
        return $this->query()
            ->with('user')
            ->when($filters['type'] ?? '', fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['risk_level'] ?? '', fn ($query, string $level) => $query->where('risk_level', $level))
            ->when($filters['status'] ?? '', fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['user_id'] ?? 0, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->paginate($pageSize);
    }
}
