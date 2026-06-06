<?php

namespace App\Modules\Basics\Model;

use App\Kernel\Base\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileAsset extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'record_id',
        'storage_file_id',
        'file_type',
        'storage_key',
        'file_url',
        'mime_type',
        'file_size',
        'ocr_text',
        'ocr_status',
        'ocr_error',
        'transcript_text',
        'transcript_status',
        'transcript_error',
    ];

    /**
     * 按上传用户筛选文件。
     *
     * @param Builder $builder 查询构造器
     * @param int $userId 用户 ID
     * @return Builder
     */
    public function scopeUserIdQuery(Builder $builder, int $userId): Builder
    {
        return $builder->where('user_id', $userId);
    }

    /**
     * 按关联分析记录筛选文件。
     *
     * @param Builder $builder 查询构造器
     * @param int $recordId 分析记录 ID
     * @return Builder
     */
    public function scopeRecordIdQuery(Builder $builder, int $recordId): Builder
    {
        return $builder->where('record_id', $recordId);
    }

    /**
     * 按公共文件服务编号筛选文件。
     *
     * @param Builder $builder 查询构造器
     * @param string $storageFileId 公共文件服务文件 ID
     * @return Builder
     */
    public function scopeStorageFileIdQuery(Builder $builder, string $storageFileId): Builder
    {
        return $builder->where('storage_file_id', $storageFileId);
    }

    /**
     * 按文件类型筛选：image/audio 等。
     *
     * @param Builder $builder 查询构造器
     * @param string $fileType 文件类型
     * @return Builder
     */
    public function scopeFileTypeQuery(Builder $builder, string $fileType): Builder
    {
        return $builder->where('file_type', $fileType);
    }
}
