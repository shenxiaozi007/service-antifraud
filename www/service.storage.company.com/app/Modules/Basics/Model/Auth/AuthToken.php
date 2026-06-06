<?php

namespace App\Modules\Basics\Model\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthToken extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'token',
        'client_type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
