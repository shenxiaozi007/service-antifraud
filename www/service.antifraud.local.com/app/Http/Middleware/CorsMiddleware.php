<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204, $this->headers($request));
        }

        $response = $next($request);

        foreach ($this->headers($request) as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    private function headers(Request $request): array
    {
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        $origin = $request->headers->get('Origin');
        $allowOrigin = in_array('*', $allowedOrigins, true) ? '*' : '';

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            $allowOrigin = $origin;
        }

        $headers = [
            'Access-Control-Allow-Headers' => config('cors.allowed_headers', 'Content-Type, Authorization, X-Requested-With'),
            'Access-Control-Allow-Methods' => config('cors.allowed_methods', 'GET, POST, PUT, DELETE, OPTIONS'),
            'Access-Control-Max-Age' => (string) config('cors.max_age', 86400),
            'Vary' => 'Origin',
        ];

        if ($allowOrigin !== '') {
            $headers['Access-Control-Allow-Origin'] = $allowOrigin;
        }

        return $headers;
    }
}
