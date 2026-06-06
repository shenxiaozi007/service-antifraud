<?php

namespace App\Modules\Basics\Dao\Wallet;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Wallet\ProjectWallet;
use Illuminate\Database\Eloquent\Model;

class ProjectWalletDao extends BaseDao
{
    public function __construct(protected ProjectWallet $projectWallet)
    {
    }

    protected function getModel(): Model
    {
        return $this->projectWallet;
    }

    public function findWallet(int $userId, string $projectCode): ?ProjectWallet
    {
        return $this->newBuilder()
            ->where('user_id', $userId)
            ->where('project_code', $projectCode)
            ->first();
    }

    public function lockWallet(int $userId, string $projectCode): ?ProjectWallet
    {
        return $this->newBuilder()
            ->where('user_id', $userId)
            ->where('project_code', $projectCode)
            ->lockForUpdate()
            ->first();
    }
}
