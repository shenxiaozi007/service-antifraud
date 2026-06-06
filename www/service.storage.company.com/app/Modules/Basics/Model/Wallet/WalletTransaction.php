<?php

namespace App\Modules\Basics\Model\Wallet;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'project_code',
        'transaction_no',
        'related_no',
        'amount',
        'frozen_amount',
        'balance_after',
        'frozen_after',
        'type',
        'status',
        'remark',
    ];
}
