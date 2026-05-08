<?php

namespace App\Http\Controllers\Service\V1;

use App\Kernel\Base\BaseController;
use App\Modules\Service\AnalysisBusiness;
use Illuminate\Http\Request;

class AnalysisController extends BaseController
{
    public function __construct(protected Request $request, protected AnalysisBusiness $business)
    {
    }

    public function createImage()
    {
        return $this->revert($this->business->createImage($this->request));
    }

    public function createAudio()
    {
        return $this->revert($this->business->createAudio($this->request));
    }

    public function detail(int $recordId)
    {
        return $this->revert($this->business->detail($this->request, $recordId));
    }

    public function records()
    {
        return $this->revert($this->business->records($this->request));
    }

    public function delete(int $recordId)
    {
        return $this->revert($this->business->delete($this->request, $recordId));
    }
}
