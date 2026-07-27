<?php

use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Filament\Imports\RedirectImporter;
use VanOns\FilamentRedirects\Models\Redirect;

function importRow(array $data): void
{
    $columnMap = collect(RedirectImporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])
        ->all();

    $importer = new RedirectImporter(new Import(), $columnMap, []);
    $importer($data);
}

function baseRow(array $overrides = []): array
{
    return array_merge([
        'from' => 'old-page',
        'to' => 'new-page',
        'type' => 'static',
        'status_code' => '301',
        'include_headers' => false,
        'include_query' => false,
        'category' => null,
        'title' => null,
    ], $overrides);
}

it('imports a valid row into a redirect', function () {
    importRow(baseRow());

    expect(Redirect::where('from', 'old-page')->where('to', 'new-page')->exists())->toBeTrue();
});

it('trims surrounding slashes from from/to on import', function () {
    importRow(baseRow(['from' => '/old-page/', 'to' => '/new-page/']));

    expect(Redirect::where('from', 'old-page')->where('to', 'new-page')->exists())->toBeTrue();
});

it('rejects an import row with a match pattern that does not compile', function () {
    expect(fn () => importRow(baseRow(['from' => '(unclosed', 'type' => 'match'])))
        ->toThrow(ValidationException::class);

    expect(Redirect::where('from', '(unclosed')->exists())->toBeFalse();
});

it('allows an unanchored regex-looking value for a static import row', function () {
    importRow(baseRow(['from' => '(unclosed', 'type' => 'static']));

    expect(Redirect::where('from', '(unclosed')->exists())->toBeTrue();
});

it('rejects an import row where to equals from', function () {
    expect(fn () => importRow(baseRow(['from' => 'same-page', 'to' => 'same-page'])))
        ->toThrow(ValidationException::class);

    expect(Redirect::where('from', 'same-page')->exists())->toBeFalse();
});

it('rejects an import row that would create a circular redirect', function () {
    Redirect::create(['from' => 'b', 'to' => 'a', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    expect(fn () => importRow(baseRow(['from' => 'a', 'to' => 'b'])))
        ->toThrow(ValidationException::class);

    expect(Redirect::where('from', 'a')->exists())->toBeFalse();
});
