<?php

namespace App\Modules\Basics\Dao\Payment;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Payment\PaymentOrder;
use Illuminate\Database\Eloquent\Model;

class PaymentOrderDao extends BaseDao
{
    public function __construct(protected PaymentOrder $paymentOrder)
    {
    }

    protected function getModel(): Model
    {
        return $this->paymentOrder;
    }

    public function findByOrderNo(string $orderNo): ?PaymentOrder
    {
        return $this->newBuilder()->where('order_no', $orderNo)->first();
    }

    public function lockByOrderNo(string $orderNo): ?PaymentOrder
    {
        return $this->newBuilder()->where('order_no', $orderNo)->lockForUpdate()->first();
    }
}
