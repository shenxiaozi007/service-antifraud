<?php

namespace App\Modules\Service\Business;

use App\Exceptions\Common\AppException;
use App\Exceptions\Common\FileUploadException;
use App\Kernel\Base\BaseBusiness;
use App\Libraries\Storage\S3StorageClient;
use App\Modules\Basics\Constant\Common\StorageDisk;
use App\Modules\Basics\Dao\File\FileObjectDao;
use App\Modules\Basics\Model\File\FileObject;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileBusiness extends BaseBusiness
{
    public function __construct(
        protected FileObjectDao $fileObjectDao,
        protected S3StorageClient $storageClient,
    ) {
    }

    public function upload(?UploadedFile $file, ?string $fileName = '', ?string $disk = ''): array
    {
        if (! $file || ! $file->isValid()) {
            throw new FileUploadException(800005);
        }

        $uploadConfig = config('upload');
        $fileMime = $file->getMimeType() ?: 'application/octet-stream';
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($fileMime, $uploadConfig['allow_type'], true)) {
            throw new FileUploadException(800013, [], '不允许上传此类型的文件! mime：' . $fileMime);
        }

        if ($ext === '') {
            $ext = strtolower(pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        }

        if ($ext === '' || in_array($ext, $uploadConfig['not_allow_ext'], true)) {
            throw new FileUploadException(800004);
        }

        if ($file->getSize() > (int) $uploadConfig['max_file_size']) {
            throw new FileUploadException(800014);
        }

        $disk = StorageDisk::getWithDefault($disk);
        $filePath = $file->getRealPath();
        $hash = md5_file($filePath);
        $originName = $fileName ?: $file->getClientOriginalName();
        $sameFile = $this->fileObjectDao->findSameObject($hash, $disk);

        if ($sameFile && $sameFile->original_name === $originName) {
            return $this->formatFile($sameFile);
        }

        $objectKey = $sameFile?->object_key ?: $this->buildObjectKey($ext);
        $storage = $this->storageClient->setDisk($disk);

        if (! $sameFile) {
            $storage->upload($objectKey, $filePath, $fileMime);
        }

        $fileObject = $this->fileObjectDao->store([
            'file_id' => storage_file_id(),
            'disk' => $disk,
            'bucket' => $storage->getBucket(),
            'object_key' => $objectKey,
            'original_name' => $originName,
            'mime_type' => $fileMime,
            'extension' => $ext,
            'size' => $file->getSize(),
            'hash' => $hash,
            'status' => FileObject::STATUS_NORMAL,
        ]);

        return $this->formatFile($fileObject);
    }

    public function detail(string $fileId): array
    {
        validator(['file_id' => $fileId], [
            'file_id' => ['required', 'string'],
        ])->validate();

        $file = $this->fileObjectDao->findByFileId($fileId);
        if (! $file) {
            throw new AppException(110006);
        }

        return $this->formatFile($file);
    }

    public function downloadUrl(array $params): array
    {
        validator($params, [
            'file_id' => ['required', 'string'],
            'expires' => ['nullable', 'integer', 'min:0', 'max:604800'],
        ])->validate();

        $file = $this->fileObjectDao->findByFileId($params['file_id']);
        if (! $file) {
            throw new AppException(110006);
        }

        return [
            'file_id' => $file->file_id,
            'download_url' => $this->getFileUrl($file, (int) array_get($params, 'expires', 0)),
        ];
    }

    public function download(string $fileId): StreamedResponse
    {
        return $this->streamFile($fileId, 'attachment');
    }

    public function preview(string $fileId): StreamedResponse
    {
        return $this->streamFile($fileId, 'inline');
    }

    protected function streamFile(string $fileId, string $disposition): StreamedResponse
    {
        $file = $this->fileObjectDao->findByFileId($fileId);
        if (! $file) {
            throw new AppException(110006);
        }

        $object = $this->storageClient->setDisk($file->disk)->getObject($file->object_key);
        $body = $object['Body'];

        $response = response()->stream(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(8192);
            }
        }, 200, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => $disposition . '; filename="' . rawurlencode($file->original_name) . '"',
        ]);

        return $response;
    }

    public function getFileUrl(FileObject $fileObject, int $expires = 0): string
    {
        return $this->storageClient
            ->setDisk($fileObject->disk)
            ->getUrl($fileObject->object_key, $expires);
    }

    public function disks(): array
    {
        return StorageDisk::getNames();
    }

    protected function buildObjectKey(string $ext): string
    {
        return date('Y/m/d') . '/' . storage_file_id() . '.' . $ext;
    }

    protected function formatFile(FileObject $fileObject): array
    {
        return [
            'file_id' => $fileObject->file_id,
            'disk' => $fileObject->disk,
            'bucket' => $fileObject->bucket,
            'object_key' => $fileObject->object_key,
            'original_name' => $fileObject->original_name,
            'mime_type' => $fileObject->mime_type,
            'extension' => $fileObject->extension,
            'size' => $fileObject->size,
            'hash' => $fileObject->hash,
            'file_url' => $fileObject->file_url,
        ];
    }
}
