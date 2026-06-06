<?php

namespace Tests;

use App\Libraries\CommonService\CommonServiceClient;
use App\Libraries\Agent\ContentExtractionService;
use App\Modules\Basics\Constant\PointConstant;
use App\Modules\Basics\Dao\AnalysisRecordDao;
use App\Modules\Basics\Dao\RiskItemDao;
use App\Modules\Basics\Model\AnalysisRecord;
use App\Modules\Basics\Model\FileAsset;
use App\Modules\Basics\Model\User;
use App\Modules\Service\AnalysisBusiness;
use App\Modules\Service\RiskAnalysisBusiness;
use App\Modules\Service\UserBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalysisBusinessRulesTest extends TestCase
{
    public function test_wallet_related_no_includes_retry_count(): void
    {
        $business = $this->analysisBusinessWithoutConstructor();
        $method = new \ReflectionMethod(AnalysisBusiness::class, 'walletRelatedNo');
        $method->setAccessible(true);

        $record = new AnalysisRecord();
        $record->id = 123;
        $record->retry_count = 2;

        $this->assertSame('analysis:123:2', $method->invoke($business, $record));
    }

    public function test_record_cost_points_are_calculated_from_type(): void
    {
        $business = $this->analysisBusinessWithoutConstructor();
        $method = new \ReflectionMethod(AnalysisBusiness::class, 'recordCostPoints');
        $method->setAccessible(true);

        $image = new AnalysisRecord();
        $image->type = 'image';

        $audio = new AnalysisRecord();
        $audio->type = 'audio';
        $audio->duration_seconds = 121;

        $this->assertSame(PointConstant::IMAGE_ANALYSIS_POINTS, $method->invoke($business, $image));
        $this->assertSame(30, $method->invoke($business, $audio));
    }

    public function test_mark_record_failed_clears_frozen_points(): void
    {
        $business = $this->analysisBusinessWithoutConstructor();
        $method = new \ReflectionMethod(AnalysisBusiness::class, 'markRecordFailed');
        $method->setAccessible(true);

        $record = new InMemoryAnalysisRecord();
        $record->status = 'processing';
        $record->frozen_points = 20;

        $method->invoke($business, $record, new \RuntimeException('boom'));

        $this->assertSame('failed', $record->status);
        $this->assertSame('boom', $record->error_message);
        $this->assertSame(0, $record->frozen_points);
        $this->assertTrue($record->saved);
    }

    public function test_release_frozen_points_calls_common_wallet_release(): void
    {
        $client = new FakeCommonServiceClient();
        $business = $this->analysisBusinessWithoutConstructor();
        $this->setCommonServiceClient($business, $client);

        $method = new \ReflectionMethod(AnalysisBusiness::class, 'releaseFrozenPoints');
        $method->setAccessible(true);

        $record = new InMemoryAnalysisRecord();
        $record->id = 456;
        $record->retry_count = 3;
        $record->setRelation('user', (object) ['global_user_id' => 10001]);

        $method->invoke($business, $record, 20, '分析失败退回点数');

        $this->assertSame([[
            'global_user_id' => 10001,
            'amount' => 20,
            'related_no' => 'analysis:456:3',
            'remark' => '分析失败退回点数',
        ]], $client->releaseCalls);
    }

    public function test_release_frozen_points_skips_zero_amount(): void
    {
        $client = new FakeCommonServiceClient();
        $business = $this->analysisBusinessWithoutConstructor();
        $this->setCommonServiceClient($business, $client);

        $method = new \ReflectionMethod(AnalysisBusiness::class, 'releaseFrozenPoints');
        $method->setAccessible(true);

        $record = new InMemoryAnalysisRecord();
        $record->id = 456;
        $record->retry_count = 3;
        $record->setRelation('user', (object) ['global_user_id' => 10001]);

        $method->invoke($business, $record, 0, '分析失败退回点数');

        $this->assertSame([], $client->releaseCalls);
    }

    public function test_process_record_releases_frozen_points_when_confirm_fails_after_report_is_saved(): void
    {
        $record = new InMemoryProcessAnalysisRecord();
        $record->id = 789;
        $record->retry_count = 1;
        $record->frozen_points = 20;
        $record->summary = '保证收益，稳赚不赔。';
        $record->setRelation('user', (object) ['global_user_id' => 10002]);
        $record->setRelation('fileAssets', collect());

        DB::shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $client = new ConfirmFailingCommonServiceClient();
        $business = $this->analysisBusinessWithoutConstructor();
        $this->setTypedProperty($business, 'analysisRecordDao', new InMemoryProcessAnalysisRecordDao($record));
        $this->setTypedProperty($business, 'riskItemDao', new InMemoryRiskItemDao());
        $this->setTypedProperty($business, 'riskAnalysisBusiness', new InMemoryProcessRiskAnalysisBusiness());
        $this->setCommonServiceClient($business, $client);

        $business->processRecord(789);

        $this->assertSame('failed', $record->status);
        $this->assertSame('confirm failed', $record->error_message);
        $this->assertSame(0, $record->frozen_points);
        $this->assertSame([[
            'global_user_id' => 10002,
            'amount' => 20,
            'related_no' => 'analysis:789:1',
            'remark' => '分析失败退回点数',
        ]], $client->releaseCalls);
        $this->assertTrue($record->successSavedBeforeConfirm);
    }

    public function test_process_record_uses_user_text_and_skips_audio_transcription(): void
    {
        $record = new InMemoryProcessAnalysisRecord();
        $record->id = 790;
        $record->retry_count = 0;
        $record->frozen_points = 10;
        $record->summary = '不要告诉家人，把验证码发给我';
        $record->setRelation('user', (object) ['global_user_id' => 10003]);

        $file = new InMemoryProcessFileAsset([
            'id' => 9,
            'file_type' => 'audio',
            'transcript_status' => 'pending',
        ]);
        $record->setRelation('fileAssets', collect([$file]));

        DB::shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $riskBusiness = new TrackingRiskAnalysisBusiness();
        $business = $this->analysisBusinessWithoutConstructor();
        $this->setTypedProperty($business, 'analysisRecordDao', new InMemoryProcessAnalysisRecordDao($record));
        $this->setTypedProperty($business, 'riskItemDao', new InMemoryRiskItemDao());
        $this->setTypedProperty($business, 'riskAnalysisBusiness', $riskBusiness);
        $this->setTypedProperty($business, 'contentExtractionService', new FailingContentExtractionService());
        $this->setCommonServiceClient($business, new FakeCommonServiceClient());

        $business->processRecord(790);

        $this->assertSame('success', $record->status);
        $this->assertSame('不要告诉家人，把验证码发给我', $riskBusiness->lastText);
        $this->assertSame('skipped', $file->transcript_status);
        $this->assertTrue($file->saved);
    }

    public function test_process_record_fails_when_audio_has_no_user_text_and_only_fallback_text(): void
    {
        $record = new InMemoryProcessAnalysisRecord();
        $record->id = 791;
        $record->retry_count = 0;
        $record->frozen_points = 10;
        $record->summary = '';
        $record->setRelation('user', (object) ['global_user_id' => 10004]);

        $file = new InMemoryProcessFileAsset([
            'id' => 10,
            'file_type' => 'audio',
            'transcript_status' => 'pending',
        ]);
        $record->setRelation('fileAssets', collect([$file]));

        $client = new FakeCommonServiceClient();
        $business = $this->analysisBusinessWithoutConstructor();
        $this->setTypedProperty($business, 'analysisRecordDao', new InMemoryProcessAnalysisRecordDao($record));
        $this->setTypedProperty($business, 'riskItemDao', new InMemoryRiskItemDao());
        $this->setTypedProperty($business, 'riskAnalysisBusiness', new TrackingRiskAnalysisBusiness());
        $this->setTypedProperty($business, 'contentExtractionService', new FallbackContentExtractionService());
        $this->setCommonServiceClient($business, $client);

        $business->processRecord(791);

        $this->assertSame('failed', $record->status);
        $this->assertSame('音频转写不可用，请输入文字内容后重试', $record->error_message);
        $this->assertSame(0, $record->frozen_points);
        $this->assertSame([[
            'global_user_id' => 10004,
            'amount' => 10,
            'related_no' => 'analysis:791:0',
            'remark' => '分析失败退回点数',
        ]], $client->releaseCalls);
    }

    public function test_records_accepts_status_filter_and_passes_it_to_dao(): void
    {
        $dao = new InMemoryAnalysisRecordPageDao();
        $business = $this->analysisBusinessWithoutConstructor();
        $this->setTypedProperty($business, 'userBusiness', new InMemoryAnalysisUserBusiness());
        $this->setTypedProperty($business, 'analysisRecordDao', $dao);

        $result = $business->records(new Request([
            'type' => 'image',
            'risk_level' => 'high',
            'status' => 'failed',
            'page_size' => 10,
        ]));

        $this->assertSame([], $result['items']->all());
        $this->assertSame([
            'type' => 'image',
            'risk_level' => 'high',
            'status' => 'failed',
            'page_size' => 10,
        ], $dao->lastFilters);
    }

    private function analysisBusinessWithoutConstructor(): AnalysisBusiness
    {
        return (new \ReflectionClass(AnalysisBusiness::class))->newInstanceWithoutConstructor();
    }

    private function setCommonServiceClient(AnalysisBusiness $business, CommonServiceClient $client): void
    {
        $this->setTypedProperty($business, 'commonServiceClient', $client);
    }

    private function setTypedProperty(AnalysisBusiness $business, string $name, object $value): void
    {
        $property = new \ReflectionProperty(AnalysisBusiness::class, 'commonServiceClient');
        if ($name !== 'commonServiceClient') {
            $property = new \ReflectionProperty(AnalysisBusiness::class, $name);
        }
        $property->setAccessible(true);
        $property->setValue($business, $value);
    }
}

class InMemoryAnalysisRecord extends AnalysisRecord
{
    public bool $saved = false;

    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }
}

class FakeCommonServiceClient extends CommonServiceClient
{
    public array $releaseCalls = [];
    public array $confirmCalls = [];

    public function confirm(int $globalUserId, int $amount, string $relatedNo, string $remark): array
    {
        $this->confirmCalls[] = [
            'global_user_id' => $globalUserId,
            'amount' => $amount,
            'related_no' => $relatedNo,
            'remark' => $remark,
        ];

        return ['success' => true];
    }

    public function release(int $globalUserId, int $amount, string $relatedNo, string $remark): array
    {
        $this->releaseCalls[] = [
            'global_user_id' => $globalUserId,
            'amount' => $amount,
            'related_no' => $relatedNo,
            'remark' => $remark,
        ];

        return ['success' => true];
    }
}

class ConfirmFailingCommonServiceClient extends FakeCommonServiceClient
{
    public function confirm(int $globalUserId, int $amount, string $relatedNo, string $remark): array
    {
        throw new \RuntimeException('confirm failed');
    }
}

class InMemoryRiskItemDao extends RiskItemDao
{
    public array $replaceCalls = [];

    public function __construct()
    {
    }

    public function replaceForRecord(int $recordId, array $items): void
    {
        $this->replaceCalls[] = compact('recordId', 'items');
    }
}

class InMemoryProcessAnalysisRecordDao extends AnalysisRecordDao
{
    public function __construct(private $record)
    {
    }

    public function findWithDetail(int $recordId): ?AnalysisRecord
    {
        return $this->record;
    }
}

class InMemoryAnalysisRecordPageDao extends AnalysisRecordDao
{
    public array $lastFilters = [];

    public function __construct()
    {
    }

    public function userPage(int $userId, array $filters, int $pageSize = 20)
    {
        $this->lastFilters = $filters;

        return new InMemoryPaginator();
    }
}

class InMemoryAnalysisUserBusiness extends UserBusiness
{
    public function __construct()
    {
    }

    public function currentUser(Request $request)
    {
        $user = new User();
        $user->id = 10001;

        return $user;
    }
}

class InMemoryPaginator
{
    public function items(): array
    {
        return [];
    }

    public function total(): int
    {
        return 0;
    }

    public function currentPage(): int
    {
        return 1;
    }

    public function perPage(): int
    {
        return 10;
    }
}

class InMemoryProcessRiskAnalysisBusiness extends RiskAnalysisBusiness
{
    public function __construct()
    {
    }

    public function analyze(string $text): array
    {
        return [
            'title' => '疑似诈骗',
            'risk_level' => 'high',
            'risk_score' => 80,
            'summary' => '存在高风险话术。',
            'suggestions' => ['暂停转账'],
            'risk_items' => [
                ['category' => '收益承诺', 'severity' => 'high', 'description' => '承诺稳赚不赔'],
            ],
        ];
    }
}

class TrackingRiskAnalysisBusiness extends InMemoryProcessRiskAnalysisBusiness
{
    public string $lastText = '';

    public function analyze(string $text): array
    {
        $this->lastText = $text;

        return parent::analyze($text);
    }
}

class FailingContentExtractionService extends ContentExtractionService
{
    public function __construct()
    {
    }

    public function extract($file): array
    {
        throw new \RuntimeException('should not transcribe');
    }
}

class FallbackContentExtractionService extends ContentExtractionService
{
    public function __construct()
    {
    }

    public function extract($file): array
    {
        return [
            'text' => '文件 file_abc 已上传，当前未配置识别供应商，暂使用文件URL和用户补充文本作为识别输入：https://example.com/audio.webm',
            'status' => 'success',
        ];
    }
}

class InMemoryProcessFileAsset extends FileAsset
{
    public bool $saved = false;

    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }
}

class InMemoryProcessAnalysisRecord extends InMemoryAnalysisRecord
{
    public bool $successSavedBeforeConfirm = false;

    public function save(array $options = []): bool
    {
        if ($this->status === 'success' && $this->frozen_points === 0 && $this->cost_points === 20) {
            $this->successSavedBeforeConfirm = true;
        }

        return parent::save($options);
    }
}
