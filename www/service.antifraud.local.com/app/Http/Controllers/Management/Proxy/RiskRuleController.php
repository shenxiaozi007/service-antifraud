<?php

namespace App\Http\Controllers\Management\Proxy;

use App\Kernel\Base\BaseController;
use App\Modules\Management\Business\RiskRuleBusiness;
use Illuminate\Http\Request;

class RiskRuleController extends BaseController
{
    public function __construct(protected Request $request, protected RiskRuleBusiness $business)
    {
    }

    public function list()
    {
        return $this->revert($this->business->list($this->request));
    }

    public function store()
    {
        return $this->revert($this->business->store($this->request));
    }

    public function update(int $ruleId)
    {
        return $this->revert($this->business->update($this->request, $ruleId));
    }
}
