<?php

namespace App\Exceptions;

use App\Exceptions\Common\AppException;
use App\Kernel\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponseTrait;

    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    public function render($request, Throwable $e)
    {
        if ($e instanceof BaseException) {
            return $this->ok($e->all());
        }

        if ($e instanceof ValidationException) {
            $errors = $e->errors();

            return $this->ok((new AppException(
                100003,
                $errors,
                '参数错误：' . implode(', ', array_flatten($errors))
            ))->all());
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->error((new AppException(100001))->all());
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->ok((new AppException(100007))->all());
        }

        return $this->error((new AppException(100000, [], $e->getMessage()))->all());
    }
}
