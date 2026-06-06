<?php

namespace App\Modules\Basics\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileAsset extends Model
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
}
