<?php

namespace App\Modules\Management\Business;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Dao\AnalysisRecordDao;
use App\Modules\Basics\Dao\FileAssetDao;
use App\Modules\Basics\Dao\PointTransactionDao;
use App\Modules\Basics\Dao\UserDao;
use App\Modules\Service\AnalysisBusiness;
use Illuminate\Http\Request;

class AdminBusiness extends BaseBusiness
{
    public function __construct(
        protected UserDao $userDao,
        protected AnalysisRecordDao $analysisRecordDao,
        protected FileAssetDao $fileAssetDao,
        protected PointTransactionDao $pointTransactionDao,
        protected AnalysisBusiness $analysisBusiness
    ) {
    }

    public function users(Request $request): array
    {
        $filters = $this->validate($request->all(), [
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

    public function records(Request $request): array
    {
        $filters = $this->validate($request->all(), [
            'type' => 'nullable|string|max:20',
            'risk_level' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:20',
            'user_id' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $page = $this->analysisRecordDao->adminPage($filters, (int) ($filters['page_size'] ?? 20));

        return $this->page($page, fn ($record) => $this->analysisBusiness->formatRecord($record));
    }

    public function recordDetail(int $recordId): array
    {
        $record = $this->analysisRecordDao->findWithDetail($recordId);
        if (!$record) {
            $this->fail(404, '分析记录不存在');
        }

        return $this->analysisBusiness->formatRecord($record, true);
    }

    public function files(Request $request): array
    {
        $filters = $this->validate($request->all(), [
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

    public function pointTransactions(Request $request): array
    {
        $filters = $this->validate($request->all(), [
            'user_id' => 'nullable|integer|min:1',
            'type' => 'nullable|string|max:30',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $page = $this->pointTransactionDao->adminPage($filters, (int) ($filters['page_size'] ?? 20));

        return $this->page($page, fn ($item) => [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'related_record_id' => $item->related_record_id,
            'amount' => $item->amount,
            'balance_after' => $item->balance_after,
            'type' => $item->type,
            'status' => $item->status,
            'remark' => $item->remark,
            'created_at' => $this->datetimeString($item->created_at),
        ]);
    }

    public function retry(int $recordId): array
    {
        return $this->analysisBusiness->retry($recordId);
    }

    private function page($page, callable $formatter): array
    {
        return [
            'items' => collect($page->items())->map($formatter)->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
        ];
    }
}
