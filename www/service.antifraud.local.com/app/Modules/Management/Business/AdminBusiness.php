<?php

namespace App\Modules\Management\Business;

use App\Kernel\Base\BaseBusiness;
use App\Libraries\CommonService\CommonServiceClient;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Dao\AnalysisRecordDao;
use App\Modules\Basics\Dao\FileAssetDao;
use App\Modules\Basics\Dao\UserDao;
use App\Modules\Service\AnalysisBusiness;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class AdminBusiness extends BaseBusiness
{
    /**
     * @param UserDao $userDao 用户 Dao
     * @param AnalysisRecordDao $analysisRecordDao 分析记录 Dao
     * @param FileAssetDao $fileAssetDao 文件 Dao
     * @param AnalysisBusiness $analysisBusiness 分析业务服务
     * @param CommonServiceClient $commonServiceClient 公共服务客户端
     */
    public function __construct(
        protected UserDao $userDao,
        protected AnalysisRecordDao $analysisRecordDao,
        protected FileAssetDao $fileAssetDao,
        protected AnalysisBusiness $analysisBusiness,
        protected CommonServiceClient $commonServiceClient
    ) {
    }

    /**
     * 管理端用户列表。
     *
     * @param array $params 查询参数：keyword、page_size
     * @return array
     */
    public function users(array $params): array
    {
        $filters = $this->validate($params, [
            'keyword' => 'nullable|string|max:128',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $page = $this->userDao->page($filters, (int) ($filters['page_size'] ?? 20));

        return $this->page($page, fn ($user) => [
            'id' => $user->id,
            'openid' => $user->openid,
            'nickname' => $user->nickname,
            'points_balance' => $user->points_balance,
            'status' => $user->status,
            'last_login_at' => $this->datetimeString($user->last_login_at),
            'created_at' => $this->datetimeString($user->created_at),
        ]);
    }

    /**
     * 管理端分析记录列表。
     *
     * @param array $params 查询参数：type、risk_level、status、user_id、page_size
     * @return array
     */
    public function records(array $params): array
    {
        $filters = $this->validate($params, [
            'type' => ['nullable', Rule::in(AnalysisConstant::types())],
            'risk_level' => ['nullable', Rule::in(AnalysisConstant::riskLevels())],
            'status' => ['nullable', Rule::in(AnalysisConstant::statuses())],
            'user_id' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $page = $this->analysisRecordDao->adminPage($filters, (int) ($filters['page_size'] ?? 20));

        return $this->page($page, fn ($record) => $this->analysisBusiness->formatRecord($record));
    }

    /**
     * 管理端分析记录详情。
     *
     * @param int $recordId 分析记录 ID
     * @return array
     */
    public function recordDetail(int $recordId): array
    {
        $record = $this->analysisRecordDao->findWithDetail($recordId);
        if (!$record) {
            $this->fail(404, '分析记录不存在');
        }

        return $this->analysisBusiness->formatRecord($record, true);
    }

    /**
     * 管理端文件列表。
     *
     * @param array $params 查询参数：file_type、user_id、page_size
     * @return array
     */
    public function files(array $params): array
    {
        $filters = $this->validate($params, [
            'file_type' => 'nullable|string|max:20',
            'user_id' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $page = $this->fileAssetDao->page($filters, (int) ($filters['page_size'] ?? 20));

        return $this->page($page, fn ($file) => [
            'id' => $file->id,
            'user_id' => $file->user_id,
            'record_id' => $file->record_id,
            'file_type' => $file->file_type,
            'storage_key' => $file->storage_key,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'created_at' => $this->datetimeString($file->created_at),
        ]);
    }

    /**
     * 管理端积分流水。
     *
     * @param array $params 查询参数：user_id、page_size
     * @return array
     */
    public function pointTransactions(array $params): array
    {
        $filters = $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $user = $this->userDao->find((int) $filters['user_id']);
        if (!$user || (int) $user->global_user_id <= 0) {
            $this->fail(404, '用户不存在或未绑定公共用户');
        }

        return $this->commonServiceClient->transactionsByUser((int) $user->global_user_id, [
            'page_size' => (int) ($filters['page_size'] ?? 20),
        ]);
    }

    /**
     * 管理端重试失败的分析记录。
     *
     * @param int $recordId 分析记录 ID
     * @return array
     */
    public function retry(int $recordId): array
    {
        return $this->analysisBusiness->retry($recordId);
    }

    /**
     * 统一分页响应格式，兼容当前前端已有字段。
     *
     * @param LengthAwarePaginator $page 分页对象
     * @param callable $formatter 单行数据格式化回调
     * @return array
     */
    private function page(LengthAwarePaginator $page, callable $formatter): array
    {
        return [
            'items' => collect($page->items())->map($formatter)->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
        ];
    }
}
