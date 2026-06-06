<?php

namespace App\Modules\Basics\Model;

use App\Kernel\Base\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends BaseModel
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

    /**
     * 管理端用户列表关键字搜索。
     *
     * keyword 同时匹配 openid 和昵称，保持原接口的搜索口径。
     *
     * @param Builder $builder 查询构造器
     * @param string $keyword 搜索关键字
     * @return Builder
     */
    public function scopeKeywordQuery(Builder $builder, string $keyword): Builder
    {
        return $builder->where(function (Builder $query) use ($keyword) {
            $query->where('openid', 'like', "%{$keyword}%")
                ->orWhere('nickname', 'like', "%{$keyword}%");
        });
    }

    /**
     * API token 登录态查询。
     *
     * @param Builder $builder 查询构造器
     * @param string $token API token
     * @return Builder
     */
    public function scopeApiTokenQuery(Builder $builder, string $token): Builder
    {
        return $builder->where('api_token', $token);
    }

    /**
     * 小程序 openid 查询。
     *
     * @param Builder $builder 查询构造器
     * @param string $openid 小程序 openid
     * @return Builder
     */
    public function scopeOpenidQuery(Builder $builder, string $openid): Builder
    {
        return $builder->where('openid', $openid);
    }

    /**
     * 公共用户编号查询。
     *
     * @param Builder $builder 查询构造器
     * @param int $globalUserId 公共用户 ID
     * @return Builder
     */
    public function scopeGlobalUserIdQuery(Builder $builder, int $globalUserId): Builder
    {
        return $builder->where('global_user_id', $globalUserId);
    }

    /**
     * 用户状态查询，1 表示启用。
     *
     * @param Builder $builder 查询构造器
     * @param int $status 用户状态
     * @return Builder
     */
    public function scopeStatusQuery(Builder $builder, int $status): Builder
    {
        return $builder->where('status', $status);
    }
}
