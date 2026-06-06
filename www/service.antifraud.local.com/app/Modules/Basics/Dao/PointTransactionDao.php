<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\PointTransaction;
class PointTransactionDao extends BaseDao
{
    /**
     * @param PointTransaction $model 积分流水模型
     */
    public function __construct(PointTransaction $model)
    {
        parent::__construct($model);
    }

    /**
     * 用户端积分流水分页列表。
     *
     * @param int $userId 用户 ID
     * @param int $pageSize 每页条数
     * @return LengthAwarePaginator
     */
    public function userPage(int $userId, int $pageSize = 20)
    {
        return $this->getPageList([
            'user_id' => $userId,
            'page_size' => $pageSize,
            'sort_by_id_desc' => true,
        ]);
    }

    /**
     * 管理端积分流水分页列表。
     *
     * @param array $filters 查询条件：user_id、type、page_size
     * @param int $pageSize 每页条数
     * @return LengthAwarePaginator
     */
    public function adminPage(array $filters, int $pageSize = 20)
    {
        return $this->getPageList(array_merge($filters, [
            'page_size' => $pageSize,
            'sort_by_id_desc' => true,
        ]));
    }
}
