<?php

namespace App\Modules\Basics\Dao\Wallet;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Wallet\WalletTransaction;
use Illuminate\Database\Eloquent\Model;

class WalletTransactionDao extends BaseDao
{
    public function __construct(protected WalletTransaction $walletTransaction)
    {
    }

    protected function getModel(): Model
    {
        return $this->walletTransaction;
    }

    public function page(int $userId, string $projectCode, int $pageSize = 20)
    {
        return $this->newBuilder()
            ->where('user_id', $userId)
            ->where('project_code', $projectCode)
            ->orderByDesc('id')
            ->paginate($pageSize);
    }

    public function existsRelated(string $relatedNo, string $type): bool
    {
        return $this->newBuilder()
            ->where('related_no', $relatedNo)
            ->where('type', $type)
            ->exists();
    }
}
