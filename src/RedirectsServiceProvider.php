<?php

namespace VanOns\FilamentRedirects;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use VanOns\FilamentRedirects\Middleware\RedirectMiddleware;

class RedirectsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-redirects');

        $this->mergeConfigFrom(__DIR__.'/../config/filament-redirects.php', 'filament-redirects');

        if (config('filament-redirects.add_middleware', true)) {
            $this->app->make(Kernel::class)->pushMiddleware(RedirectMiddleware::class);
        }

        if ($this->app->runningInConsole()) {
            if (method_exists($this, 'publishesMigrations')) {
                $this->publishesMigrations(
                    paths: [
                        __DIR__.'/../database/migrations' => database_path('migrations'),
                    ],
                    groups: 'vanons-filament-redirects-migrations',
                );
            } else {
                // Laravel < 10
                $this->publishes(
                    paths: [
                        __DIR__.'/../database/migrations' => database_path('migrations'),
                    ],
                    groups: 'vanons-filament-redirects-migrations',
                );
            }

            $this->publishes(
                paths: [
                    __DIR__.'/../config/filament-redirects.php' => config_path('filament-redirects.php'),
                ],
                groups: 'vanons-filament-redirects-config'
            );

            $this->publishes(
                paths: [
                    __DIR__.'/../lang' => lang_path('vendor/filament-redirects'),
                ],
                groups: 'vanons-filament-redirects-translations'
            );
        }
    }
}