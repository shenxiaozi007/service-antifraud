<?php

namespace App\Kernel\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function response(array $data = [], int $status = 200, array $headers = [], int $options = JSON_UNESCAPED_UNICODE): JsonResponse
    {
        return response()->json($data, $status, $headers, $options);
    }

    protected function ok(array $data, array $headers = [], int $options = JSON_UNESCAPED_UNICODE): JsonResponse
    {
        return $this->response($data, 200, $headers, $options);
    }

    protected function error(array $data, array $headers = [], int $options = JSON_UNESCAPED_UNICODE): JsonResponse
    {
        return $this->response($data, 500, $headers, $options);
    }
}
