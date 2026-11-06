<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use VanOns\FilamentRedirects\RedirectsServiceProvider;

function publishedMigrations(): array
{
    return collect(File::glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $name): bool => str_starts_with($name, '0001_01_01_'))
        ->values()
        ->all();
}

// Laravel only sends CommandFinished outside unit tests, so the tests send it themselves.
function outputAfter(string $command): string
{
    event(new CommandFinished($command, new ArrayInput([]), $output = new BufferedOutput(), 0));

    return $output->fetch();
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

it('names the migrations an app has neither published nor run after migrate', function () {
    DB::table('migrations')->delete();

    expect(outputAfter('migrate'))
        ->toContain('Migrations of filament-redirects that are not published: ')
        ->toContain('create_redirects_table')
        ->toContain('php artisan vendor:publish --tag=filament-redirects-migrations')
        ->and(outputAfter('migrate:status'))->toBe('');
});

it('stays quiet once the migrations are published', function () {
    DB::table('migrations')->delete();
    $this->artisan('vendor:publish', ['--tag' => 'filament-redirects-migrations'])->assertSuccessful();

    expect(outputAfter('migrate'))->toBe('');
});

it('stays quiet about migrations that ran but whose files are gone, as after schema:dump --prune', function () {
    DB::table('migrations')->delete();

    foreach (File::files(__DIR__.'/../../database/migrations') as $file) {
        DB::table('migrations')->insert([
            'migration' => '2020_01_01_000000_'.preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $file->getBasename('.php')),
            'batch' => 1,
        ]);
    }

    expect(outputAfter('migrate'))->toBe('');
});
