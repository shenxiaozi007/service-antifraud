<?php

namespace App\Modules\Basics\Model\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommonUser extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'nickname',
        'avatar_url',
        'status',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];
}
