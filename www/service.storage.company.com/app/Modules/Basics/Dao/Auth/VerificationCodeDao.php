<?php

namespace App\Modules\Basics\Dao\Auth;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Auth\VerificationCode;
use Illuminate\Database\Eloquent\Model;

class VerificationCodeDao extends BaseDao
{
    public function __construct(protected VerificationCode $verificationCode)
    {
    }

    protected function getModel(): Model
    {
        return $this->verificationCode;
    }

    public function latestPending(string $account, string $scene): ?VerificationCode
    {
        return $this->newBuilder()
            ->where('account', $account)
            ->where('scene', $scene)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
    }
}
