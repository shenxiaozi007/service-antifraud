<?php

namespace App\Modules\Basics\Model\File;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileObject extends Model
{
    use SoftDeletes;

    public const STATUS_NORMAL = 1;

    protected $table = 'file_objects';

    protected $guarded = [];

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute(): string
    {
        return app(\App\Modules\Service\Business\FileBusiness::class)->getFileUrl($this);
    }

    public function scopeFileIdQuery(Builder $query, $value): void
    {
        $query->where('file_id', $value);
    }

    public function scopeHashQuery(Builder $query, $value): void
    {
        $query->where('hash', $value);
    }

    public function scopeDiskQuery(Builder $query, $value): void
    {
        $query->where('disk', $value);
    }
}
