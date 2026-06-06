<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\AnalysisRecord;
class AnalysisRecordDao extends BaseDao
{
    /**
     * @param AnalysisRecord $model 分析记录模型
     */
    public function __construct(AnalysisRecord $model)
    {
        parent::__construct($model);
    }

    /**
     * 查询用户自己的记录详情。
     *
     * 用确定参数表达查询意图，避免调用方传泛化 params 拼查询。
     *
     * @param int $recordId 分析记录 ID
     * @param int $userId 用户 ID
     * @return AnalysisRecord|null
     */
    public function findUserRecord(int $recordId, int $userId): ?AnalysisRecord
    {
        return $this->newBuilder()
            ->with(['riskItems', 'fileAssets'])
            ->where('id', $recordId)
            ->userIdQuery($userId)
            ->first();
    }

    /**
     * 查询包含风险项、文件和用户信息的完整记录详情。
     *
     * @param int $recordId 分析记录 ID
     * @return AnalysisRecord|null
     */
    public function findWithDetail(int $recordId): ?AnalysisRecord
    {
        return $this->find($recordId, relations: ['riskItems', 'fileAssets', 'user']);
    }

    /**
     * 用户端分析记录分页列表。
     *
     * @param int $userId 用户 ID
     * @param array $filters 查询条件：type、risk_level、status、page_size
     * @param int $pageSize 每页条数
     * @return LengthAwarePaginator
     */
    public function userPage(int $userId, array $filters, int $pageSize = 20)
    {
        return $this->getPageList(array_merge($filters, [
            'user_id' => $userId,
            'page_size' => $pageSize,
            'sort_by_id_desc' => true,
        ]));
    }

    /**
     * 管理端分析记录分页列表。
     *
     * @param array $filters 查询条件：type、risk_level、status、user_id、page_size
     * @param int $pageSize 每页条数
     * @return LengthAwarePaginator
     */
    public function adminPage(array $filters, int $pageSize = 20)
    {
        return $this->getPageList(array_merge($filters, [
            'page_size' => $pageSize,
            'sort_by_id_desc' => true,
        ]), relations: ['user']);
    }
}
