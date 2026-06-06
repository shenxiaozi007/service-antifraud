<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Libraries\Agent\ContentExtractionService;
use App\Libraries\CommonService\CommonServiceClient;
use App\Jobs\Analysis\AnalyzeRiskJob;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Constant\PointConstant;
use App\Modules\Basics\Dao\AnalysisRecordDao;
use App\Modules\Basics\Dao\FileAssetDao;
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
        protected RiskAnalysisBusiness $riskAnalysisBusiness,
        protected CommonServiceClient $commonServiceClient,
        protected ContentExtractionService $contentExtractionService
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
            $data['text'] ?? ''
        );
    }

    public function createAudio(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'file_id' => 'nullable|integer|min:1',
            'duration_seconds' => 'required|integer|min:1|max:7200',
            'text' => 'required_without:file_id|nullable|string|max:50000',
        ]);

        $fileIds = [];
        if (!empty($data['file_id'])) {
            $file = $this->fileAssetDao->findUserFile((int) $data['file_id'], $user->id);
            if (!$file) {
                $this->fail(422, '文件不存在或无权访问');
            }
            if ($file->file_type !== AnalysisConstant::TYPE_AUDIO) {
                $this->fail(422, '只能提交录音文件');
            }
            $fileIds[] = (int) $data['file_id'];
        }

        $costPoints = (int) ceil($data['duration_seconds'] / 60) * PointConstant::AUDIO_ANALYSIS_POINTS_PER_MINUTE;

        return $this->createRecord(
            $user,
            AnalysisConstant::TYPE_AUDIO,
            $costPoints,
            0,
            (int) $data['duration_seconds'],
            $fileIds,
            $data['text'] ?? ''
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
            'status' => ['nullable', Rule::in(AnalysisConstant::statuses())],
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

        $costPoints = $this->recordCostPoints($record);
        $record->increment('retry_count');
        $record = $record->refresh()->load(['riskItems', 'fileAssets', 'user']);

        $this->commonServiceClient->freeze($record->user->global_user_id, $costPoints, $this->walletRelatedNo($record), '分析重试冻结点数');
        $record->fill([
            'status' => AnalysisConstant::STATUS_PENDING,
            'error_message' => null,
            'frozen_points' => $costPoints,
            'cost_points' => 0,
        ])->save();

        dispatch(new AnalyzeRiskJob($record->id));

        return $this->formatRecord($record->refresh()->load(['riskItems', 'fileAssets']), true);
    }

    private function createRecord($user, string $type, int $costPoints, int $imageCount, int $durationSeconds, array $fileIds, string $text): array
    {
        $record = DB::transaction(function () use ($user, $type, $costPoints, $imageCount, $durationSeconds, $fileIds, $text) {
            $record = $this->analysisRecordDao->create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $type === AnalysisConstant::TYPE_IMAGE ? '图片风险分析中' : '录音风险分析中',
                'risk_level' => AnalysisConstant::RISK_LOW,
                'risk_score' => 0,
                'summary' => $text,
                'suggestions' => [],
                'status' => AnalysisConstant::STATUS_PENDING,
                'cost_points' => 0,
                'frozen_points' => $costPoints,
                'image_count' => $imageCount,
                'duration_seconds' => $durationSeconds,
            ]);

            $this->fileAssetDao->bindRecord($fileIds, $record->id, $user->id);

            return $record;
        });

        try {
            $this->commonServiceClient->freeze($user->global_user_id, $costPoints, $this->walletRelatedNo($record), $type === AnalysisConstant::TYPE_IMAGE ? '图片分析冻结点数' : '录音分析冻结点数');
        } catch (\Throwable $e) {
            $record->delete();
            throw $e;
        }

        dispatch(new AnalyzeRiskJob($record->id));
        $record = $record->refresh()->load(['riskItems', 'fileAssets']);

        return [
            'record_id' => $record->id,
            'status' => $record->status,
            'frozen_points' => $record->frozen_points,
            'cost_points' => $costPoints,
            'report' => $this->formatRecord($record, true),
        ];
    }

    public function processRecord(int $recordId): void
    {
        $record = $this->analysisRecordDao->findWithDetail($recordId);
        if (!$record) {
            return;
        }

        $record->fill(['status' => AnalysisConstant::STATUS_PROCESSING, 'error_message' => null])->save();

        try {
            $textParts = [];
            if ($record->summary) {
                $textParts[] = $record->summary;
            }
            foreach ($record->fileAssets as $file) {
                try {
                    $textParts[] = $this->contentExtractionService->extract($file)['text'];
                } catch (\Throwable $exception) {
                    if (!$record->summary) {
                        throw $exception;
                    }

                    $this->markFileExtractionFailed($file, $exception);
                }
            }
            $text = trim(implode("\n", array_filter($textParts)));
            $result = $this->riskAnalysisBusiness->analyze($text);
            $costPoints = (int) $record->frozen_points;
            $relatedNo = $this->walletRelatedNo($record);

            $this->fillSuccessRecord($record, $result, $costPoints);
            $this->commonServiceClient->confirm($record->user->global_user_id, $costPoints, $relatedNo, '分析成功扣点');
        } catch (\Throwable $e) {
            $this->releaseFrozenPoints($record, $costPoints ?? (int) $record->frozen_points, '分析失败退回点数');
            $this->markRecordFailed($record, $e);
        }
    }

    private function fillSuccessRecord($record, array $result, int $costPoints): void
    {
        DB::transaction(function () use ($record, $result, $costPoints) {
            $record->fill([
                'title' => $result['title'],
                'risk_level' => $result['risk_level'],
                'risk_score' => $result['risk_score'],
                'summary' => $result['summary'],
                'suggestions' => $result['suggestions'],
                'status' => AnalysisConstant::STATUS_SUCCESS,
                'cost_points' => $costPoints,
                'frozen_points' => 0,
                'llm_model' => $result['llm_model'] ?? '',
                'llm_duration_ms' => $result['llm_duration_ms'] ?? 0,
                'llm_raw_output' => $result['llm_raw_output'] ?? null,
                'analyzed_at' => Carbon::now(),
            ])->save();

            $this->riskItemDao->replaceForRecord($record->id, $result['risk_items']);
        });
    }

    private function markRecordFailed($record, \Throwable $e): void
    {
        $record->fill([
            'status' => AnalysisConstant::STATUS_FAILED,
            'error_message' => $e->getMessage(),
            'frozen_points' => 0,
        ])->save();
    }

    private function markFileExtractionFailed($file, \Throwable $exception): void
    {
        if ($file->file_type === AnalysisConstant::TYPE_IMAGE) {
            $file->fill([
                'ocr_status' => 'failed',
                'ocr_error' => mb_substr($exception->getMessage(), 0, 500),
            ])->save();

            return;
        }

        $file->fill([
            'transcript_status' => 'failed',
            'transcript_error' => mb_substr($exception->getMessage(), 0, 500),
        ])->save();
    }

    private function releaseFrozenPoints($record, int $frozenPoints, string $remark): void
    {
        if ($frozenPoints > 0) {
            $this->commonServiceClient->release($record->user->global_user_id, $frozenPoints, $this->walletRelatedNo($record), $remark);
        }
    }

    private function recordCostPoints($record): int
    {
        if ($record->type === AnalysisConstant::TYPE_AUDIO) {
            return max(1, (int) ceil(max(1, (int) $record->duration_seconds) / 60)) * PointConstant::AUDIO_ANALYSIS_POINTS_PER_MINUTE;
        }

        return PointConstant::IMAGE_ANALYSIS_POINTS;
    }

    private function walletRelatedNo($record): string
    {
        return sprintf('analysis:%d:%d', $record->id, (int) $record->retry_count);
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
            'error_message' => $record->error_message,
            'cost_points' => $record->cost_points,
            'frozen_points' => $record->frozen_points,
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
