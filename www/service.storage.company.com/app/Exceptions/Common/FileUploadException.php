<?php

namespace App\Exceptions\Common;

use App\Exceptions\BaseException;

class FileUploadException extends BaseException
{
    protected static array $codeMaps = [
        800004 => ['message' => '文件格式错误！'],
        800005 => ['message' => '文件获取失败！'],
        800006 => ['message' => '没找到对应的文件信息！'],
        800012 => ['message' => '文件上传失败！'],
        800013 => ['message' => '不允许上传此类型的文件!'],
        800014 => ['message' => '上传的文件过大!'],
        800017 => ['message' => '文件下载失败!'],
    ];
}
