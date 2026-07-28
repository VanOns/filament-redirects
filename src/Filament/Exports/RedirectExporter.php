<?php

namespace VanOns\FilamentRedirects\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;
use VanOns\FilamentRedirects\Models\Redirect;

class RedirectExporter extends Exporter
{
    protected static ?string $model = Redirect::class;

    public static function getColumns(): array
    {
        return [
            self::escaped(ExportColumn::make('from')
                ->label(__('filament-redirects::general.from'))),
            self::escaped(ExportColumn::make('to')
                ->label(__('filament-redirects::general.to'))),
            self::escaped(ExportColumn::make('type')
                ->label(__('filament-redirects::general.type'))),
            ExportColumn::make('status_code')
                ->label(__('filament-redirects::general.status_code')),
            ExportColumn::make('include_headers')
                ->label(__('filament-redirects::general.include_headers')),
            ExportColumn::make('include_query')
                ->label(__('filament-redirects::general.include_query')),
            self::escaped(ExportColumn::make('category')
                ->label(__('filament-redirects::general.category'))),
            self::escaped(ExportColumn::make('title')
                ->label(__('filament-redirects::general.title'))),
            ExportColumn::make('active')
                ->label(__('filament-redirects::general.active')),
            ExportColumn::make('hits')
                ->label(__('filament-redirects::general.hits')),
            ExportColumn::make('last_hit')
                ->label(__('filament-redirects::general.last_hit')),
            ExportColumn::make('priority')
                ->label(__('filament-redirects::general.priority')),
            ExportColumn::make('created_at')
                ->label(__('filament-redirects::general.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('filament-redirects::general.updated_at')),
        ];
    }

    // falls back to an equivalent implementation on Filament versions without this method
    private static function escaped(ExportColumn $column): ExportColumn
    {
        if (method_exists($column, 'preventFormulaInjection')) {
            return $column->preventFormulaInjection();
        }

        return $column->formatStateUsing(fn (mixed $state): mixed => self::sanitizeFormulaState($state));
    }

    public static function sanitizeFormulaState(mixed $state): mixed
    {
        if (!is_string($state) || $state === '') {
            return $state;
        }

        if (in_array($state[0], ['-', '+'], true) && is_numeric($state)) {
            return $state;
        }

        if (in_array($state[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$state;
        }

        return $state;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('filament-redirects::general.export_success', ['successful_rows' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= __('filament-redirects::general.export_rows_failed', ['failed_rows' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
