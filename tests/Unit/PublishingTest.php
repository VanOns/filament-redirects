<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use VanOns\FilamentRedirects\RedirectsServiceProvider;

function publishedMigrations(): array
{
    return collect(File::glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $name): bool => str_starts_with($name, '0001_01_01_'))
        ->values()
        ->all();
}

afterEach(function () {
    foreach (publishedMigrations() as $name) {
        File::delete(database_path("migrations/{$name}"));
    }
});

it('publishes each migration once, also next to ones an app published before', function () {
    File::copy(
        __DIR__.'/../../database/migrations/2024_09_10_130742_create_redirects_table.php',
        database_path('migrations/2025_01_01_000000_create_redirects_table.php'),
    );
    $this->refreshApplication();

    $this->artisan('vendor:publish', ['--tag' => 'filament-redirects-migrations'])->assertSuccessful();
    $this->artisan('vendor:publish', ['--tag' => 'vanons-filament-redirects-migrations'])->assertSuccessful();

    expect(publishedMigrations())
        ->toHaveCount(count(File::files(__DIR__.'/../../database/migrations')))
        ->toContain('2025_01_01_000000_create_redirects_table.php')
        ->and(preg_grep('/_create_redirects_table\.php$/', publishedMigrations()))->toHaveCount(1);
});

it('keeps the tags from before v2.7', function (string $old, string $new) {
    expect(ServiceProvider::pathsToPublish(RedirectsServiceProvider::class, $old))
        ->not->toBeEmpty()
        ->toBe(ServiceProvider::pathsToPublish(RedirectsServiceProvider::class, $new));
})->with([
    ['vanons-filament-redirects-migrations', 'filament-redirects-migrations'],
    ['vanons-filament-redirects-config', 'filament-redirects-config'],
    ['vanons-filament-redirects-translations', 'filament-redirects-lang'],
]);
