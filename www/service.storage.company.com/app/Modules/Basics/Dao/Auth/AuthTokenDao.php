<?php

namespace App\Modules\Basics\Dao\Auth;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Auth\AuthToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AuthTokenDao extends BaseDao
{
    public function __construct(protected AuthToken $authToken)
    {
    }

    protected function getModel(): Model
    {
        return $this->authToken;
    }

    public function findValidToken(string $token): ?AuthToken
    {
        return $this->newBuilder()
            ->where('token', $token)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->first();
    }
}
