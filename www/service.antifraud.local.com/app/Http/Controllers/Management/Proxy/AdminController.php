<?php

namespace App\Http\Controllers\Management\Proxy;

use App\Kernel\Base\BaseController;
use App\Modules\Management\Business\AdminBusiness;
use Illuminate\Http\Request;

class AdminController extends BaseController
{
    public function __construct(protected Request $request, protected AdminBusiness $business)
    {
    }

    public function users()
    {
        return $this->revert($this->business->users($this->request));
    }

    public function records()
    {
        return $this->revert($this->business->records($this->request));
    }

    public function recordDetail(int $recordId)
    {
        return $this->revert($this->business->recordDetail($recordId));
    }

    public function files()
    {
        return $this->revert($this->business->files($this->request));
    }

    public function pointTransactions()
    {
        return $this->revert($this->business->pointTransactions($this->request));
    }

    public function retry(int $recordId)
    {
        return $this->revert($this->business->retry($recordId));
    }
}
