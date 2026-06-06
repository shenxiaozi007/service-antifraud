<?php

namespace App\Modules\Basics\Model\Auth;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    protected $fillable = [
        'account',
        'scene',
        'code',
        'status',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
