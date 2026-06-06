<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Dao\FileAssetDao;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FileBusiness extends BaseBusiness
{
    public function __construct(
        protected UserBusiness $userBusiness,
        protected FileAssetDao $fileAssetDao
    ) {
    }

    public function uploadToken(Request $request): array
    {
        $this->userBusiness->currentUser($request);
        $this->validate($request->all(), [
            'file_type' => ['required', Rule::in(AnalysisConstant::types())],
            'mime_type' => 'required|string|max:100',
            'file_size' => 'required|integer|min:1|max:52428800',
        ]);

        return [
            'upload_url' => rtrim(config('common_service.base_url'), '/').'/file/upload',
            'upload_method' => 'multipart',
            'register_url' => '/api/v1/files/register',
            'owner_project' => config('common_service.project_code', 'antifraud'),
            'message' => '请先上传到公共文件服务，再调用当前服务 files/register 绑定业务文件。',
        ];
    }

    public function register(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'storage_file_id' => 'required|string|max:64',
            'file_type' => ['required', Rule::in(AnalysisConstant::types())],
            'object_key' => 'required|string|max:512',
            'file_url' => 'nullable|string|max:1024',
            'mime_type' => 'nullable|string|max:100',
            'file_size' => 'nullable|integer|min:0',
        ]);

        $exists = $this->fileAssetDao->findByStorageFileId($data['storage_file_id'], $user->id);
        if ($exists) {
            return $this->formatFile($exists);
        }

        $file = $this->fileAssetDao->create([
            'user_id' => $user->id,
            'storage_file_id' => $data['storage_file_id'],
            'file_type' => $data['file_type'],
            'storage_key' => $data['object_key'],
            'file_url' => $data['file_url'] ?? '',
            'mime_type' => $data['mime_type'] ?? '',
            'file_size' => (int) ($data['file_size'] ?? 0),
            'ocr_status' => $data['file_type'] === AnalysisConstant::TYPE_IMAGE ? 'pending' : 'skipped',
            'transcript_status' => $data['file_type'] === AnalysisConstant::TYPE_AUDIO ? 'pending' : 'skipped',
        ]);

        return $this->formatFile($file);
    }

    protected function formatFile($file): array
    {
        return [
            'file_id' => $file->id,
            'storage_file_id' => $file->storage_file_id,
            'file_type' => $file->file_type,
            'storage_key' => $file->storage_key,
            'file_url' => $file->file_url,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
        ];
    }

}
