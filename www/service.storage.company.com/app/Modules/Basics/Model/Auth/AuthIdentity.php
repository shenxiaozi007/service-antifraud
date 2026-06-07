<?php

namespace App\Modules\Basics\Model\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthIdentity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'identity_type',
        'identifier',
        'unionid',
        'extra',
        'password_hash',
        'password_updated_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'extra' => 'array',
        'password_updated_at' => 'datetime',
    ];
}
