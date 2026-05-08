<?php

namespace App\Kernel\Base;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class BaseBusiness
{
    protected function validate(array $data, array $rules, array $attributes = []): array
    {
        $validator = Validator::make($data, $rules, [], $attributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    protected function bearerToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return (string) $request->input('token', '');
    }

    protected function fail(int $statusCode, string $message): void
    {
        throw new HttpException($statusCode, $message);
    }

    protected function datetimeString($value): ?string
    {
        return $value ? $value->toDateTimeString() : null;
    }
}
