<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\RiskItem;

class RiskItemDao extends BaseDao
{
    public function __construct(RiskItem $model)
    {
        parent::__construct($model);
    }

    public function replaceForRecord(int $recordId, array $items): void
    {
        $this->query()->where('record_id', $recordId)->delete();

        foreach ($items as $item) {
            $this->create([
                'record_id' => $recordId,
                'category' => $item['category'],
                'severity' => $item['severity'],
                'description' => $item['description'],
                'evidence_text' => $item['evidence_text'] ?? null,
            ]);
        }
    }
}
