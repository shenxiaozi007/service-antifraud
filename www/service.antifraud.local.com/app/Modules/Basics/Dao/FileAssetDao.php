<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\FileAsset;

class FileAssetDao extends BaseDao
{
    public function __construct(FileAsset $model)
    {
        parent::__construct($model);
    }

    public function findUserFile(int $fileId, int $userId): ?FileAsset
    {
        return $this->query()->where('id', $fileId)->where('user_id', $userId)->first();
    }

    public function bindRecord(array $fileIds, int $recordId, int $userId): void
    {
        $this->query()
            ->whereIn('id', $fileIds)
            ->where('user_id', $userId)
            ->update(['record_id' => $recordId]);
    }

    public function page(array $filters, int $pageSize = 20)
    {
        return $this->query()
            ->when($filters['file_type'] ?? '', fn ($query, string $type) => $query->where('file_type', $type))
            ->when($filters['user_id'] ?? 0, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->paginate($pageSize);
    }
}
