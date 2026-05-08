<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Constant\AnalysisConstant;
use App\Modules\Basics\Dao\FileAssetDao;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'file_type' => ['required', Rule::in(AnalysisConstant::types())],
            'mime_type' => 'required|string|max:100',
            'file_size' => 'required|integer|min:1|max:52428800',
        ]);

        $storageKey = sprintf(
            'uploads/%d/%s/%s',
            $user->id,
            Carbon::now()->format('Y/m/d'),
            Str::uuid().$this->guessExtension($data['mime_type'])
        );

        $file = $this->fileAssetDao->create([
            'user_id' => $user->id,
            'file_type' => $data['file_type'],
            'storage_key' => $storageKey,
            'file_url' => '/storage/'.$storageKey,
            'mime_type' => $data['mime_type'],
            'file_size' => $data['file_size'],
        ]);

        return [
            'file_id' => $file->id,
            'upload_url' => '/api/v1/files/local-upload-placeholder',
            'storage_key' => $storageKey,
        ];
    }

    private function guessExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => '.png',
            'image/webp' => '.webp',
            'audio/mpeg' => '.mp3',
            'audio/mp4' => '.m4a',
            'audio/wav', 'audio/x-wav' => '.wav',
            default => '.jpg',
        };
    }
}
