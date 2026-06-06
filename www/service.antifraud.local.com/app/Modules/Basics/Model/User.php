<?php

namespace App\Modules\Basics\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'openid',
        'global_user_id',
        'project_code',
        'unionid',
        'email',
        'mobile',
        'nickname',
        'avatar_url',
        'points_balance',
        'status',
        'api_token',
        'last_login_at',
    ];

    protected $hidden = ['api_token'];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];
}
