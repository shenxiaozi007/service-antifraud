<?php

namespace App\Modules\Basics\Model\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_no',
        'user_id',
        'project_code',
        'package_id',
        'points',
        'amount_cent',
        'channel',
        'status',
        'prepay_id',
        'transaction_id',
        'payment_params',
        'notify_payload',
        'paid_at',
    ];

    protected $casts = [
        'payment_params' => 'array',
        'notify_payload' => 'array',
        'paid_at' => 'datetime',
    ];
}
