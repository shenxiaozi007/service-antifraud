<?php

namespace Tests;

use App\Libraries\Agent\LlmClient;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Dao\RiskRuleDao;
use App\Modules\Service\RiskAnalysisBusiness;
use Illuminate\Support\Collection;

class RiskAnalysisBusinessTest extends TestCase
{
    public function test_llm_success_result_is_normalized_to_report_contract(): void
    {
        $business = new RiskAnalysisBusiness(
            new InMemoryRiskRuleDao(),
            new FakeRiskLlmClient([
                'enabled' => true,
                'success' => true,
                'model' => 'risk-model',
                'duration_ms' => 123,
                'raw' => ['choices' => []],
                'result' => [
                    'risk_level' => 'critical',
                    'risk_score' => 101,
                    'title' => '疑似验证码诈骗',
                    'summary' => '对方索要验证码。',
                    'suggestions' => ['停止提供验证码'],
                    'risk_items' => [
                        ['category' => '敏感信息', 'severity' => 'critical', 'description' => '索要验证码', 'evidence_text' => '验证码'],
                    ],
                ],
            ])
        );

        $result = $business->analyze('请把验证码告诉我');

        $this->assertSame(AnalysisConstant::RISK_CRITICAL, $result['risk_level']);
        $this->assertSame(100, $result['risk_score']);
        $this->assertSame('疑似验证码诈骗', $result['title']);
        $this->assertSame('risk-model', $result['llm_model']);
        $this->assertSame(123, $result['llm_duration_ms']);
        $this->assertSame('敏感信息', $result['risk_items'][0]['category']);
    }

    public function test_llm_failure_falls_back_to_keyword_rules(): void
    {
        $business = new RiskAnalysisBusiness(
            new InMemoryRiskRuleDao(),
            new FakeRiskLlmClient([
                'enabled' => true,
                'success' => false,
                'model' => 'risk-model',
                'duration_ms' => 88,
                'raw' => 'timeout',
            ])
        );

        $result = $business->analyze('请不要告诉家人，把验证码发我，并转到个人账户。');

        $this->assertSame(AnalysisConstant::RISK_CRITICAL, $result['risk_level']);
        $this->assertGreaterThanOrEqual(75, $result['risk_score']);
        $this->assertSame('risk-model', $result['llm_model']);
        $this->assertSame('timeout', $result['llm_raw_output']);
        $this->assertNotEmpty($result['risk_items']);
    }
}

class InMemoryRiskRuleDao extends RiskRuleDao
{
    public function __construct()
    {
    }

    public function enabledRules()
    {
        return new Collection();
    }
}

class FakeRiskLlmClient extends LlmClient
{
    public function __construct(private array $response)
    {
    }

    public function analyze(string $prompt): array
    {
        return $this->response;
    }
}
