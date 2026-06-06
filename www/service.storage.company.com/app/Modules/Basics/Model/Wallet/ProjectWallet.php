<?php

namespace App\Modules\Basics\Model\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectWallet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_code',
        'balance',
        'frozen_balance',
    ];
}
