<?php

namespace VanOns\FilamentRedirects;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use VanOns\FilamentRedirects\Commands\CheckDestinationsCommand;
use VanOns\FilamentRedirects\Listeners\WarnAboutUnpublishedMigrations;
use VanOns\FilamentRedirects\Middleware\RedirectMiddleware;

class RedirectsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // Recognises migrations an app already published, whatever their date, so publishing again only adds new ones.
        $package
            ->name('filament-redirects')
            ->discoversMigrations();
    }

    public function packageBooted(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'filament-redirects');

        $this->mergeConfigFrom(__DIR__.'/../config/filament-redirects.php', 'filament-redirects');

        if (config('filament-redirects.add_middleware', true)) {
            $this->app->make(Kernel::class)->pushMiddleware(RedirectMiddleware::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CheckDestinationsCommand::class]);

            $this->app['events']->listen(CommandFinished::class, [
                new WarnAboutUnpublishedMigrations(static::class, $this->package->name, $this->package->shortName().'-migrations'),
                'handle',
            ]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $cron = config('filament-redirects.checks.schedule');

                if (!config('filament-redirects.checks.enabled') || blank($cron)) {
                    return;
                }

                $schedule->command(CheckDestinationsCommand::class)->cron($cron);
            });

            // The vanons- tags are from before v2.7; they keep working until v3.
            $this->publishes(
                paths: static::pathsToPublish(static::class, 'filament-redirects-migrations'),
                groups: 'vanons-filament-redirects-migrations',
            );

            $this->publishes(
                paths: [
                    __DIR__.'/../config/filament-redirects.php' => config_path('filament-redirects.php'),
                ],
                groups: ['filament-redirects-config', 'vanons-filament-redirects-config'],
            );

            $this->publishes(
                paths: [
                    __DIR__.'/../lang' => lang_path('vendor/filament-redirects'),
                ],
                groups: ['filament-redirects-lang', 'vanons-filament-redirects-translations'],
            );
        }
    }
}
