<?php

namespace App\Jobs\Analysis;

use App\Jobs\Job;
use App\Modules\Service\AnalysisBusiness;

class AnalyzeRiskJob extends Job
{
    public function __construct(protected int $recordId)
    {
    }

    public function handle(AnalysisBusiness $analysisBusiness): void
    {
        $analysisBusiness->processRecord($this->recordId);
    }
}
