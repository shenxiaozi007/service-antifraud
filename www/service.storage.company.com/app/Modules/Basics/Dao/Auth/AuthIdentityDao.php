<?php

namespace App\Modules\Basics\Dao\Auth;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Auth\AuthIdentity;
use Illuminate\Database\Eloquent\Model;

class AuthIdentityDao extends BaseDao
{
    public function __construct(protected AuthIdentity $authIdentity)
    {
    }

    protected function getModel(): Model
    {
        return $this->authIdentity;
    }

    public function findIdentity(string $type, string $identifier): ?AuthIdentity
    {
        return $this->newBuilder()
            ->where('identity_type', $type)
            ->where('identifier', $identifier)
            ->first();
    }

    public function findUserIdentity(int $userId, string $type): ?AuthIdentity
    {
        return $this->newBuilder()
            ->where('user_id', $userId)
            ->where('identity_type', $type)
            ->first();
    }
}
