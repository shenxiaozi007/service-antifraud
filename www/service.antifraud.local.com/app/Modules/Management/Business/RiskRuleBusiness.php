<?php

namespace App\Modules\Management\Business;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Dao\RiskRuleDao;
use App\Modules\Basics\Model\RiskRule;
use Illuminate\Validation\Rule;

class RiskRuleBusiness extends BaseBusiness
{
    /**
     * @param RiskRuleDao $riskRuleDao 风险规则 Dao
     */
    public function __construct(protected RiskRuleDao $riskRuleDao)
    {
    }

    /**
     * 风险规则分页列表。
     *
     * Controller 传入原始请求数组，这里第一步完成校验并只保留允许字段。
     *
     * @param array $params 查询参数：category、enabled、page_size
     * @return array
     */
    public function list(array $params): array
    {
        $filters = $this->validate($params, [
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

    /**
     * 新增风险规则。
     *
     * @param array $params 写入参数：category、keyword、severity、weight、enabled
     * @return array
     */
    public function store(array $params): array
    {
        $data = $this->validateData($params);
        $rule = $this->riskRuleDao->store($data);

        return $this->format($rule);
    }

    /**
     * 更新风险规则。
     *
     * @param array $params 更新参数：category、keyword、severity、weight、enabled
     * @param int $ruleId 风险规则 ID
     * @return array
     */
    public function update(array $params, int $ruleId): array
    {
        $rule = $this->riskRuleDao->find($ruleId);
        if (!$rule) {
            $this->fail(404, '风险规则不存在');
        }

        $data = $this->validateData($params);
        $rule = $this->riskRuleDao->updateModel($rule, $data);

        return $this->format($rule);
    }

    /**
     * 风险规则写入参数校验。
     *
     * validator 返回值会过滤掉未声明字段，避免请求中的多余字段进入 Dao。
     *
     * @param array $params 原始写入参数
     * @return array
     */
    private function validateData(array $params): array
    {
        return $this->validate($params, [
            'category' => 'required|string|max:64',
            'keyword' => 'required|string|max:255',
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'weight' => 'required|integer|min:1|max:100',
            'enabled' => 'required|integer|in:0,1',
        ]);
    }

    /**
     * 风险规则对外输出格式。
     *
     * @param RiskRule $rule 风险规则模型
     * @return array
     */
    private function format(RiskRule $rule): array
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
