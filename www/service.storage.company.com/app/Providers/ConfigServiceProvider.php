<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Finder\Finder;

class ConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (Finder::create()->files()->name('*.php')->in($this->app->basePath('config')) as $file) {
            $name = str_replace('.php', '', $file->getFileName());
            $prefix = $file->getRelativePath() ? strtr($file->getRelativePath(), '/', '.') . '.' : '';

            config([$prefix . $name => require $file->getRealPath()]);
        }
    }
}
