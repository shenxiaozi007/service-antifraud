<?php

namespace App\Modules\Management\Business;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Dao\RiskRuleDao;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiskRuleBusiness extends BaseBusiness
{
    public function __construct(protected RiskRuleDao $riskRuleDao)
    {
    }

    public function list(Request $request): array
    {
        $filters = $this->validate($request->all(), [
            'category' => 'nullable|string|max:64',
            'enabled' => 'nullable|integer|in:0,1',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $page = $this->riskRuleDao->page($filters, (int) ($filters['page_size'] ?? 20));

        return [
            'items' => collect($page->items())->map(fn ($rule) => $this->format($rule))->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
        ];
    }

    public function store(Request $request): array
    {
        $data = $this->validateData($request);
        $rule = $this->riskRuleDao->create($data);

        return $this->format($rule);
    }

    public function update(Request $request, int $ruleId): array
    {
        $rule = $this->riskRuleDao->find($ruleId);
        if (!$rule) {
            $this->fail(404, '风险规则不存在');
        }

        $data = $this->validateData($request);
        $rule->fill($data)->save();

        return $this->format($rule);
    }

    private function validateData(Request $request): array
    {
        return $this->validate($request->all(), [
            'category' => 'required|string|max:64',
            'keyword' => 'required|string|max:255',
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'weight' => 'required|integer|min:1|max:100',
            'enabled' => 'required|integer|in:0,1',
        ]);
    }

    private function format($rule): array
    {
        return [
            'id' => $rule->id,
            'category' => $rule->category,
            'keyword' => $rule->keyword,
            'severity' => $rule->severity,
            'weight' => $rule->weight,
            'enabled' => $rule->enabled,
            'created_at' => $this->datetimeString($rule->created_at),
            'updated_at' => $this->datetimeString($rule->updated_at),
        ];
    }
}
