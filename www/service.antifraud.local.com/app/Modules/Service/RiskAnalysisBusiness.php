<?php

namespace App\Modules\Service;

use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Dao\RiskRuleDao;
use Illuminate\Support\Collection;

class RiskAnalysisBusiness
{
    public function __construct(protected RiskRuleDao $riskRuleDao)
    {
    }

    public function analyze(string $text): array
    {
        $rules = $this->rules();
        $riskItems = [];
        $score = 0;

        foreach ($rules as $rule) {
            if ($rule['keyword'] !== '' && str_contains($text, $rule['keyword'])) {
                $riskItems[] = [
                    'category' => $rule['category'],
                    'severity' => $rule['severity'],
                    'description' => $this->description($rule['category']),
                    'evidence_text' => $this->maskSensitive($rule['keyword']),
                ];
                $score += (int) $rule['weight'];
            }
        }

        $score = min(100, $score);
        $riskLevel = $this->riskLevel($score, $riskItems);

        return [
            'risk_level' => $riskLevel,
            'risk_score' => $score,
            'title' => $this->title($riskLevel, $riskItems),
            'summary' => $this->summary($riskLevel),
            'suggestions' => $this->suggestions($riskLevel),
            'risk_items' => array_slice($riskItems, 0, 8),
        ];
    }

    private function rules(): array
    {
        $dbRules = $this->riskRuleDao->enabledRules();
        if ($dbRules instanceof Collection && $dbRules->isNotEmpty()) {
            return $dbRules->map(fn ($rule) => $rule->only(['category', 'keyword', 'severity', 'weight']))->all();
        }

        return [
            ['category' => '保本高收益', 'keyword' => '保证收益', 'severity' => 'high', 'weight' => 25],
            ['category' => '保本高收益', 'keyword' => '稳赚不赔', 'severity' => 'high', 'weight' => 25],
            ['category' => '内幕消息', 'keyword' => '内部消息', 'severity' => 'high', 'weight' => 20],
            ['category' => '紧急施压', 'keyword' => '名额有限', 'severity' => 'medium', 'weight' => 15],
            ['category' => '私下转账', 'keyword' => '个人账户', 'severity' => 'critical', 'weight' => 35],
            ['category' => '隐瞒家人', 'keyword' => '不要告诉家人', 'severity' => 'critical', 'weight' => 35],
            ['category' => '敏感信息', 'keyword' => '验证码', 'severity' => 'critical', 'weight' => 40],
            ['category' => '远程控制', 'keyword' => '共享屏幕', 'severity' => 'critical', 'weight' => 40],
            ['category' => '虚假资质', 'keyword' => '官方授权', 'severity' => 'medium', 'weight' => 10],
        ];
    }

    private function riskLevel(int $score, array $items): string
    {
        $hasCritical = collect($items)->contains(fn ($item) => $item['severity'] === 'critical');
        if ($hasCritical || $score >= 80) {
            return AnalysisConstant::RISK_CRITICAL;
        }
        if ($score >= 50) {
            return AnalysisConstant::RISK_HIGH;
        }
        if ($score >= 20) {
            return AnalysisConstant::RISK_MEDIUM;
        }

        return AnalysisConstant::RISK_LOW;
    }

    private function title(string $riskLevel, array $items): string
    {
        if ($items) {
            return '疑似'.$items[0]['category'].'风险';
        }

        return match ($riskLevel) {
            AnalysisConstant::RISK_CRITICAL => '存在极高风险信号',
            AnalysisConstant::RISK_HIGH => '存在明显风险信号',
            AnalysisConstant::RISK_MEDIUM => '存在可疑宣传信号',
            default => '暂未发现明显风险',
        };
    }

    private function summary(string $riskLevel): string
    {
        return match ($riskLevel) {
            AnalysisConstant::RISK_CRITICAL => '当前内容出现极高风险信号，建议立即停止配合，不要转账或提供验证码。',
            AnalysisConstant::RISK_HIGH => '当前内容存在明显风险信号，建议暂停付款并核实对方资质。',
            AnalysisConstant::RISK_MEDIUM => '当前内容存在可疑宣传，建议进一步核实来源、资质和收费方式。',
            default => '暂未发现明显诈骗话术，但仍建议通过官方渠道核实重要信息。',
        };
    }

    private function suggestions(string $riskLevel): array
    {
        if ($riskLevel === AnalysisConstant::RISK_LOW) {
            return ['保留材料并通过官方渠道核实', '涉及付款前先与家人确认'];
        }

        return ['不要继续转账或提供验证码', '将内容转发给家人共同确认', '要求对方提供可核验的官方资质', '必要时拨打 96110 咨询'];
    }

    private function description(string $category): string
    {
        return match ($category) {
            '保本高收益' => '对方承诺收益或暗示没有风险，属于高危投资诱导信号。',
            '内幕消息' => '对方宣称掌握内部渠道或特殊消息，信息来源难以核验。',
            '紧急施压' => '对方制造时间压力，容易诱导用户在未核实前付款。',
            '私下转账' => '对方要求绕开正规平台或转入个人账户，资金追回难度高。',
            '隐瞒家人' => '对方要求不要告知家人，存在规避外部提醒的风险。',
            '敏感信息' => '对方索要验证码、银行卡、身份证等敏感信息，风险极高。',
            '远程控制' => '对方要求共享屏幕或远程控制，可能窃取账户和验证码。',
            default => '当前内容存在需要进一步核实的风险信号。',
        };
    }

    private function maskSensitive(string $text): string
    {
        return preg_replace('/\d{6,}/', '******', $text) ?? $text;
    }
}
