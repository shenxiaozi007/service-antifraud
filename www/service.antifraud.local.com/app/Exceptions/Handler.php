<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'code' => 422,
                'message' => $exception->validator->errors()->first(),
                'data' => [
                    'errors' => $exception->errors(),
                ],
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        if ($exception instanceof HttpException) {
            return response()->json([
                'code' => $exception->getStatusCode(),
                'message' => $exception->getMessage() ?: '请求处理失败',
                'data' => [],
            ], $exception->getStatusCode(), [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'code' => 500,
            'message' => app()->environment('production') ? '服务器内部错误' : $exception->getMessage(),
            'data' => [],
        ], 500, [], JSON_UNESCAPED_UNICODE);
    }
}
