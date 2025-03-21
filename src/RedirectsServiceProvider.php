<?php

namespace VanOns\FilamentRedirects;

use Illuminate\Support\ServiceProvider;

class RedirectsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-redirects');

        $this->mergeConfigFrom(__DIR__.'/../config/filament-redirects.php', 'filament-redirects');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-redirects.php' => config_path('filament-redirects.php'),
            ], 'vanons-filament-redirects-config');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/filament-redirects'),
            ], 'vanons-filament-redirects-translations');
        }
    }
}
