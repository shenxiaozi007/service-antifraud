<?php

if (! function_exists('file_url')) {
    function file_url(string $fileId): string
    {
        $file = app(\App\Modules\Basics\Dao\File\FileObjectDao::class)->findByFileId($fileId);

        return $file->file_url ?? '';
    }
}
