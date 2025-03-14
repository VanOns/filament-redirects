<?php

namespace VanOns\FilamentRedirects;

use Illuminate\Support\ServiceProvider;

class RedirectsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-redirects');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->publishes([
            __DIR__.'/../config/filament-redirects.php' => config_path('filament-redirects.php'),
        ], 'vanons-filament-redirects-config');
        $this->mergeConfigFrom(__DIR__.'/../config/filament-redirects.php', 'filament-redirects');
    }
}
