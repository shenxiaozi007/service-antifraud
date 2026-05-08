<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Constant\PointConstant;
use App\Modules\Basics\Dao\AnalysisRecordDao;
use App\Modules\Basics\Dao\FileAssetDao;
use App\Modules\Basics\Dao\PointTransactionDao;
use App\Modules\Basics\Dao\RiskItemDao;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalysisBusiness extends BaseBusiness
{
    public function __construct(
        protected UserBusiness $userBusiness,
        protected AnalysisRecordDao $analysisRecordDao,
        protected FileAssetDao $fileAssetDao,
        protected RiskItemDao $riskItemDao,
        protected PointTransactionDao $pointTransactionDao,
        protected RiskAnalysisBusiness $riskAnalysisBusiness
    ) {
    }

    public function createImage(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'file_ids' => 'required|array|min:1|max:3',
            'file_ids.*' => 'required|integer|min:1',
            'text' => 'nullable|string|max:20000',
        ], [
            'file_ids' => '图片文件',
        ]);

        $files = collect($data['file_ids'])->map(fn ($fileId) => $this->fileAssetDao->findUserFile((int) $fileId, $user->id));
        if ($files->contains(null)) {
            $this->fail(422, '文件不存在或无权访问');
        }
        if ($files->contains(fn ($file) => $file->file_type !== AnalysisConstant::TYPE_IMAGE)) {
            $this->fail(422, '只能提交图片文件');
        }

        return $this->createRecord(
            $user,
            AnalysisConstant::TYPE_IMAGE,
            PointConstant::IMAGE_ANALYSIS_POINTS,
            count($data['file_ids']),
            0,
            $data['file_ids'],
            $data['text'] ?? $this->combineFileText($files, 'ocr_text')
        );
    }

    public function createAudio(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'file_id' => 'required|integer|min:1',
            'duration_seconds' => 'required|integer|min:1|max:7200',
            'text' => 'nullable|string|max:50000',
        ]);

        $file = $this->fileAssetDao->findUserFile((int) $data['file_id'], $user->id);
        if (!$file) {
            $this->fail(422, '文件不存在或无权访问');
        }
        if ($file->file_type !== AnalysisConstant::TYPE_AUDIO) {
            $this->fail(422, '只能提交录音文件');
        }

        $costPoints = (int) ceil($data['duration_seconds'] / 60) * PointConstant::AUDIO_ANALYSIS_POINTS_PER_MINUTE;

        return $this->createRecord(
            $user,
            AnalysisConstant::TYPE_AUDIO,
            $costPoints,
            0,
            (int) $data['duration_seconds'],
            [$data['file_id']],
            $data['text'] ?? ($file->transcript_text ?: '')
        );
    }

    public function detail(Request $request, int $recordId): array
    {
        $user = $this->userBusiness->currentUser($request);
        $record = $this->analysisRecordDao->findUserRecord($recordId, $user->id);
        if (!$record) {
            $this->fail(404, '分析记录不存在');
        }

        return $this->formatRecord($record, true);
    }

    public function records(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $filters = $this->validate($request->all(), [
            'type' => ['nullable', Rule::in(AnalysisConstant::types())],
            'risk_level' => ['nullable', Rule::in(AnalysisConstant::riskLevels())],
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);

        $page = $this->analysisRecordDao->userPage($user->id, $filters, (int) ($filters['page_size'] ?? 20));

        return $this->formatPage($page, fn ($record) => $this->formatRecord($record));
    }

    public function delete(Request $request, int $recordId): array
    {
        $user = $this->userBusiness->currentUser($request);
        $record = $this->analysisRecordDao->findUserRecord($recordId, $user->id);
        if (!$record) {
            $this->fail(404, '分析记录不存在');
        }

        DB::transaction(function () use ($record) {
            foreach ($record->fileAssets as $file) {
                $file->delete();
            }
            $record->delete();
        });

        return ['success' => true];
    }

    public function retry(int $recordId): array
    {
        $record = $this->analysisRecordDao->findWithDetail($recordId);
        if (!$record) {
            $this->fail(404, '分析记录不存在');
        }
        if ($record->status !== AnalysisConstant::STATUS_FAILED) {
            $this->fail(422, '只有失败记录可以重试');
        }

        $text = $this->combineFileText($record->fileAssets, $record->type === AnalysisConstant::TYPE_IMAGE ? 'ocr_text' : 'transcript_text');
        $result = $this->riskAnalysisBusiness->analyze($text);

        DB::transaction(function () use ($record, $result) {
            $record->fill([
                'title' => $result['title'],
                'risk_level' => $result['risk_level'],
                'risk_score' => $result['risk_score'],
                'summary' => $result['summary'],
                'suggestions' => $result['suggestions'],
                'status' => AnalysisConstant::STATUS_COMPLETED,
                'analyzed_at' => Carbon::now(),
            ])->save();

            $this->riskItemDao->replaceForRecord($record->id, $result['risk_items']);
        });

        return $this->formatRecord($record->refresh()->load(['riskItems', 'fileAssets']), true);
    }

    private function createRecord($user, string $type, int $costPoints, int $imageCount, int $durationSeconds, array $fileIds, string $text): array
    {
        if ($user->points_balance < $costPoints) {
            $this->fail(422, '点数余额不足');
        }

        return DB::transaction(function () use ($user, $type, $costPoints, $imageCount, $durationSeconds, $fileIds, $text) {
            $result = $this->riskAnalysisBusiness->analyze($text);

            $user->points_balance -= $costPoints;
            $user->save();

            $record = $this->analysisRecordDao->create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $result['title'],
                'risk_level' => $result['risk_level'],
                'risk_score' => $result['risk_score'],
                'summary' => $result['summary'],
                'suggestions' => $result['suggestions'],
                'status' => AnalysisConstant::STATUS_COMPLETED,
                'cost_points' => $costPoints,
                'frozen_points' => 0,
                'image_count' => $imageCount,
                'duration_seconds' => $durationSeconds,
                'analyzed_at' => Carbon::now(),
            ]);

            $this->fileAssetDao->bindRecord($fileIds, $record->id, $user->id);
            $this->riskItemDao->replaceForRecord($record->id, $result['risk_items']);
            $this->pointTransactionDao->create([
                'user_id' => $user->id,
                'related_record_id' => $record->id,
                'amount' => -$costPoints,
                'balance_after' => $user->points_balance,
                'type' => PointConstant::TYPE_ANALYSIS_COST,
                'status' => 'completed',
                'remark' => $type === AnalysisConstant::TYPE_IMAGE ? '图片分析扣点' : '录音分析扣点',
            ]);

            return [
                'record_id' => $record->id,
                'status' => $record->status,
                'frozen_points' => 0,
                'cost_points' => $costPoints,
                'report' => $this->formatRecord($record->load(['riskItems', 'fileAssets']), true),
            ];
        });
    }

    private function combineFileText($files, string $field): string
    {
        $text = collect($files)->pluck($field)->filter()->implode("\n");

        return $text !== '' ? $text : '未提供可识别文本，建议结合原始材料人工复核。';
    }

    public function formatRecord($record, bool $withDetail = false): array
    {
        $data = [
            'id' => $record->id,
            'type' => $record->type,
            'title' => $record->title,
            'risk_level' => $record->risk_level,
            'risk_score' => $record->risk_score,
            'summary' => $record->summary,
            'suggestions' => $record->suggestions ?: [],
            'status' => $record->status,
            'cost_points' => $record->cost_points,
            'image_count' => $record->image_count,
            'duration_seconds' => $record->duration_seconds,
            'analyzed_at' => $this->datetimeString($record->analyzed_at),
            'created_at' => $this->datetimeString($record->created_at),
        ];

        if ($withDetail) {
            $data['risk_items'] = $record->riskItems->map(fn ($item) => [
                'category' => $item->category,
                'severity' => $item->severity,
                'description' => $item->description,
                'evidence_text' => $item->evidence_text,
            ])->values();
            $data['files'] = $record->fileAssets->map(fn ($file) => [
                'id' => $file->id,
                'file_type' => $file->file_type,
                'file_url' => $file->file_url,
                'mime_type' => $file->mime_type,
                'file_size' => $file->file_size,
            ])->values();
            $data['disclaimer'] = '本报告仅作风险提醒参考，不构成法律、投资、医疗或财务建议。';
        }

        return $data;
    }

    public function formatPage($page, callable $formatter): array
    {
        return [
            'items' => collect($page->items())->map($formatter)->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
        ];
    }
}
