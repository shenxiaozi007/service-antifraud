<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\User;

class UserDao extends BaseDao
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByOpenid(string $openid): ?User
    {
        return $this->query()->where('openid', $openid)->first();
    }

    public function findByToken(string $token): ?User
    {
        return $this->query()->where('api_token', $token)->where('status', 1)->first();
    }

    public function page(array $filters, int $pageSize = 20)
    {
        return $this->query()
            ->when($filters['keyword'] ?? '', function ($query, string $keyword) {
                $query->where(function ($builder) use ($keyword) {
                    $builder->where('openid', 'like', "%{$keyword}%")
                        ->orWhere('nickname', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($pageSize);
    }
}
