<?php

namespace App\Http\Controllers\Management\Proxy;

use App\Kernel\Base\BaseController;
use App\Modules\Management\Business\RiskRuleBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskRuleController extends BaseController
{
    /**
     * @param Request $request 当前 HTTP 请求实例
     * @param RiskRuleBusiness $business 风险规则业务服务
     */
    public function __construct(protected Request $request, protected RiskRuleBusiness $business)
    {
    }

    /**
     * 风险规则分页列表。
     *
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        return $this->revert($this->business->list($this->request->all()));
    }

    /**
     * 新增风险规则。
     *
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        return $this->revert($this->business->store($this->request->all()));
    }

    /**
     * 更新风险规则。
     *
     * @param int $ruleId 风险规则 ID
     * @return JsonResponse
     */
    public function update(int $ruleId): JsonResponse
    {
        return $this->revert($this->business->update($this->request->all(), $ruleId));
    }
}
