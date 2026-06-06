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
    ];

    protected $casts = [
        'extra' => 'array',
    ];
}
