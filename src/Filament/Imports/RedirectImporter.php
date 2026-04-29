<?php

namespace VanOns\FilamentRedirects\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use VanOns\FilamentRedirects\Models\Redirect;
use VanOns\FilamentRedirects\Rules\NoCircularRedirect;
use VanOns\FilamentRedirects\Rules\NotSelfRedirect;

class RedirectImporter extends Importer
{
    protected static ?string $model = Redirect::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('from')
                ->label(__('filament-redirects::general.from'))
                ->castStateUsing(fn (?string $state) => empty($state) ? null : trim($state, '/'))
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('to')
                ->label(__('filament-redirects::general.to'))
                ->castStateUsing(fn (string $state) => empty($state) ? null : trim($state, '/'))
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('type')
                ->label(__('filament-redirects::general.type'))
                ->rules(['required', 'string', 'in:static,match,replace']),
            ImportColumn::make('status_code')
                ->label(__('filament-redirects::general.status_code'))
                ->rules(['required', 'integer', 'in:301,302,303,307,308']),
            ImportColumn::make('include_headers')
                ->label(__('filament-redirects::general.include_headers'))
                ->rules(['required', 'boolean']),
            ImportColumn::make('include_query')
                ->label(__('filament-redirects::general.include_query'))
                ->rules(['required', 'boolean']),
        ];
    }

    public function beforeSave(): void
    {
        $from = $this->data['from'] ?? null;

        $validator = validator(
            ['to' => $this->data['to'] ?? null],
            ['to' => [new NotSelfRedirect($from), new NoCircularRedirect($from)]],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    public function resolveRecord(): Redirect
    {
        return new Redirect();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('filament-redirects::general.import_success', ['successful_rows' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= __('filament-redirects::general.rows_failed', ['failed_rows' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
