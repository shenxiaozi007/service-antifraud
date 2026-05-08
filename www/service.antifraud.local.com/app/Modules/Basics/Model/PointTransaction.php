<?php

namespace App\Modules\Basics\Model;

use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'related_record_id',
        'amount',
        'balance_after',
        'type',
        'status',
        'remark',
    ];
}
