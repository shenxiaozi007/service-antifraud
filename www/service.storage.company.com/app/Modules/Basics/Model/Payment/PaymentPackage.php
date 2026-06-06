<?php

namespace App\Modules\Basics\Model\Payment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentPackage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_code',
        'name',
        'points',
        'amount_cent',
        'enabled',
        'sort',
    ];
}
