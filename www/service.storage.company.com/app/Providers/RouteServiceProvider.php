<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected array $routeFormat = [];

    protected array $routes = [
        'service' => [
            'api' => [
                'v1' => [
                    'domain' => 'domain.storage_service',
                    'prefix' => 'service/api/v1',
                    'namespace' => 'App\Http\Controllers\Service\Api\V1',
                    'middleware' => ['cors'],
                    'files' => [
                        'routes/service/api/v1/file/file.php',
                    ],
                ],
            ],
        ],
    ];

    public function boot(): void
    {
        $this->mapRoutes();
    }

    protected function mapRoutes(): void
    {
        $this->parseRoutes($this->routes);

        $domain = head(explode(':', get_http_host()));
        foreach ($this->routeFormat as $route) {
            $routeDomain = isset($route['domain']) ? config($route['domain'], '*') : '*';
            if ($routeDomain !== '*' && $domain !== $routeDomain) {
                continue;
            }

            foreach ($route['files'] as $file) {
                foreach (get_files(get_file_absolute_app_path($file)) as $value) {
                    $this->app->router->group(
                        array_only($route, ['namespace', 'prefix', 'middleware']),
                        function ($router) use ($value) {
                            require $value;
                        }
                    );
                }
            }
        }
    }

    protected function parseRoutes(array $routes): void
    {
        foreach ($routes as $routeInfo) {
            if (is_array($routeInfo) && ! isset($routeInfo['files'])) {
                $this->parseRoutes($routeInfo);
            }

            if (isset($routeInfo['files']) && filled($routeInfo['files'])) {
                $this->routeFormat[] = $routeInfo;
            }
        }
    }
}
