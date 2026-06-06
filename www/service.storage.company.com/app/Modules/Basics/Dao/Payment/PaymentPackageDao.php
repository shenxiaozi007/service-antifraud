<?php

namespace App\Modules\Basics\Dao\Payment;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Payment\PaymentPackage;
use Illuminate\Database\Eloquent\Model;

class PaymentPackageDao extends BaseDao
{
    public function __construct(protected PaymentPackage $paymentPackage)
    {
    }

    protected function getModel(): Model
    {
        return $this->paymentPackage;
    }

    public function enabledPackages(string $projectCode)
    {
        return $this->newBuilder()
            ->where('project_code', $projectCode)
            ->where('enabled', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }
}
