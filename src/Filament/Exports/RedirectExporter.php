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
            ExportColumn::make('from')
                ->label(__('filament-redirects::general.from')),
            ExportColumn::make('to')
                ->label(__('filament-redirects::general.to')),
            ExportColumn::make('type')
                ->label(__('filament-redirects::general.type')),
            ExportColumn::make('status_code')
                ->label(__('filament-redirects::general.status_code')),
            ExportColumn::make('include_headers')
                ->label(__('filament-redirects::general.include_headers')),
            ExportColumn::make('include_query')
                ->label(__('filament-redirects::general.include_query')),
            ExportColumn::make('category')
                ->label(__('filament-redirects::general.category')),
            ExportColumn::make('title')
                ->label(__('filament-redirects::general.title')),
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

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('filament-redirects::general.export_success', ['successful_rows' => Number::format($export->successful_rows)]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= __('filament-redirects::general.export_rows_failed', ['failed_rows' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
