<?php

namespace App\Http\Controllers\Service\Api\V1\File;

use App\Exceptions\Common\AppException;
use App\Exceptions\Common\FileUploadException;
use App\Kernel\Base\BaseController;
use App\Modules\Service\Business\FileBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends BaseController
{
    /**
     * 上传文件.
     *
     * @throws FileUploadException
     */
    public function upload(Request $request, FileBusiness $fileBusiness): JsonResponse|array|null
    {
        $file = array_first($request->allFiles());

        return $this->revert($fileBusiness->upload(
            $file,
            $request->get('file_name', ''),
            $request->get('disk', '')
        ));
    }

    /**
     * 获取文件详情.
     *
     * @throws AppException
     * @throws ValidationException
     */
    public function detail(Request $request, FileBusiness $fileBusiness): JsonResponse|array|null
    {
        return $this->revert($fileBusiness->detail($request->get('file_id', '')));
    }

    /**
     * 获取文件下载 URL.
     *
     * @throws AppException
     * @throws ValidationException
     */
    public function downloadUrl(Request $request, FileBusiness $fileBusiness): JsonResponse|array|null
    {
        return $this->revert($fileBusiness->downloadUrl($request->only(['file_id', 'expires'])));
    }

    /**
     * 流式下载文件.
     *
     * @throws AppException
     */
    public function download(Request $request, FileBusiness $fileBusiness): StreamedResponse
    {
        return $fileBusiness->download($request->get('file_id', ''));
    }

    /**
     * 内联预览文件.
     *
     * @throws AppException
     */
    public function preview(Request $request, FileBusiness $fileBusiness): StreamedResponse
    {
        return $fileBusiness->preview($request->get('file_id', ''));
    }

    public function disks(FileBusiness $fileBusiness): JsonResponse|array|null
    {
        return $this->revert($fileBusiness->disks());
    }
}
