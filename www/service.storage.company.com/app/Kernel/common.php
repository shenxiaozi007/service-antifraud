<?php

use Symfony\Component\Finder\Finder;

if (! function_exists('get_now')) {
    function get_now(): int
    {
        return time();
    }
}

if (! function_exists('get_http_host')) {
    function get_http_host(): string
    {
        return app('request')->server('HTTP_HOST') ?: array_get($_SERVER, 'HTTP_HOST', '');
    }
}

if (! function_exists('get_file_absolute_app_path')) {
    function get_file_absolute_app_path(string $path): string
    {
        return app()->basePath($path);
    }
}

if (! function_exists('get_files')) {
    function get_files(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        foreach (Finder::create()->files()->name('*.php')->in($path) as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }
}

if (! function_exists('storage_file_id')) {
    function storage_file_id(): string
    {
        return str_replace('.', '', uniqid('file_', true)) . random_int(1000, 9999);
    }
}
