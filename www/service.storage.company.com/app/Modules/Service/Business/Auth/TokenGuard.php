<?php

namespace App\Modules\Service\Business\Auth;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TokenGuard
{
    public function __construct(protected AuthBusiness $authBusiness)
    {
    }

    public function user(Request $request): array
    {
        $token = $this->bearerToken($request);
        if ($token === '') {
            throw new HttpException(401, '请先登录');
        }

        $result = $this->authBusiness->introspect($token);
        if (!($result['active'] ?? false)) {
            throw new HttpException(401, '登录已失效');
        }

        return $result['user'];
    }

    protected function bearerToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return (string) $request->input('token', '');
    }
}
