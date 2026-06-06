<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\User;
class UserDao extends BaseDao
{
    /**
     * @param User $model 用户模型
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * 根据小程序 openid 查询用户。
     *
     * @param string $openid 小程序 openid
     * @return User|null
     */
    public function findByOpenid(string $openid): ?User
    {
        return $this->newBuilder()->openidQuery($openid)->first();
    }

    /**
     * 根据 API token 查询启用中的用户。
     *
     * @param string $token API token
     * @return User|null
     */
    public function findByToken(string $token): ?User
    {
        return $this->newBuilder()
            ->apiTokenQuery($token)
            ->statusQuery(1)
            ->first();
    }

    /**
     * 根据公共用户编号查询启用中的业务用户。
     *
     * @param int $globalUserId 公共用户 ID
     * @return User|null
     */
    public function findByGlobalUserId(int $globalUserId): ?User
    {
        return $this->newBuilder()
            ->globalUserIdQuery($globalUserId)
            ->statusQuery(1)
            ->first();
    }

    /**
     * 管理端用户分页列表。
     *
     * 保留 page 方法名兼容旧 Business，内部统一走 BaseDao scope 查询。
     *
     * @param array $filters 查询条件：keyword、page_size
     * @param int $pageSize 每页条数
     * @return LengthAwarePaginator
     */
    public function page(array $filters, int $pageSize = 20)
    {
        return $this->getPageList(array_merge($filters, [
            'page_size' => $pageSize,
            'sort_by_id_desc' => true,
        ]));
    }
}
