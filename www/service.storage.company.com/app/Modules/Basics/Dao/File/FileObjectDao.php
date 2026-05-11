<?php

namespace App\Modules\Basics\Dao\File;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\File\FileObject;
use Illuminate\Database\Eloquent\Model;

class FileObjectDao extends BaseDao
{
    protected function getModel(): Model
    {
        return app(FileObject::class);
    }

    public function findByFileId(string $fileId): ?FileObject
    {
        return $this->findByParams(['file_id' => $fileId]);
    }

    public function findSameObject(string $hash, string $disk): ?FileObject
    {
        return $this->findByParams([
            'hash' => $hash,
            'disk' => $disk,
        ]);
    }
}
