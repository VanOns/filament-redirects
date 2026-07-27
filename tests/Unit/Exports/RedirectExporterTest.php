<?php

use Filament\Actions\Exports\Models\Export;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Filament\Exports\RedirectExporter;
use VanOns\FilamentRedirects\Models\Redirect;

function exportRow(Redirect $redirect): array
{
    $columnMap = collect(RedirectExporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])
        ->all();

    $exporter = new RedirectExporter(new Export(), $columnMap, []);

    return array_combine(array_keys($columnMap), $exporter($redirect));
}

it('escapes values that start with a formula-triggering character', function () {
    $redirect = Redirect::create([
        'from' => '=cmd|" /c calc"!A1',
        'to' => '+HYPERLINK("http://evil")',
        'type' => Type::Static,
        'status_code' => 301,
        'active' => true,
        'category' => '@SUM(1,2)',
        'title' => "-2\t3",
    ]);

    $row = exportRow($redirect);

    expect($row['from'])->toStartWith("'=")
        ->and($row['to'])->toStartWith("'+")
        ->and($row['category'])->toStartWith("'@")
        ->and($row['title'])->toStartWith("'-");
});

it('leaves an ordinary negative number unescaped', function () {
    $redirect = Redirect::create([
        'from' => 'old-page',
        'to' => 'new-page',
        'type' => Type::Static,
        'status_code' => 301,
        'active' => true,
        'title' => '-5',
    ]);

    $row = exportRow($redirect);

    expect($row['title'])->toBe('-5');
});

it('escapes formula-triggering characters directly', function () {
    expect(RedirectExporter::sanitizeFormulaState('=1+1'))->toBe("'=1+1")
        ->and(RedirectExporter::sanitizeFormulaState('+HYPERLINK(1)'))->toBe("'+HYPERLINK(1)")
        ->and(RedirectExporter::sanitizeFormulaState('@SUM(1)'))->toBe("'@SUM(1)")
        ->and(RedirectExporter::sanitizeFormulaState("\ttab"))->toBe("'\ttab");
});

it('leaves non-string and ordinary values unchanged when sanitizing', function () {
    expect(RedirectExporter::sanitizeFormulaState('-5'))->toBe('-5')
        ->and(RedirectExporter::sanitizeFormulaState('blog'))->toBe('blog')
        ->and(RedirectExporter::sanitizeFormulaState(''))->toBe('')
        ->and(RedirectExporter::sanitizeFormulaState(null))->toBeNull()
        ->and(RedirectExporter::sanitizeFormulaState(Type::Static))->toBe(Type::Static);
});

it('leaves ordinary values unescaped', function () {
    $redirect = Redirect::create([
        'from' => 'old-page',
        'to' => 'new-page',
        'type' => Type::Replace,
        'status_code' => 301,
        'active' => true,
        'category' => 'blog',
        'title' => 'Old page redirect',
    ]);

    $row = exportRow($redirect);

    expect($row['from'])->toBe('old-page')
        ->and($row['to'])->toBe('new-page')
        ->and($row['type'])->toBe('replace')
        ->and($row['category'])->toBe('blog')
        ->and($row['title'])->toBe('Old page redirect');
});
