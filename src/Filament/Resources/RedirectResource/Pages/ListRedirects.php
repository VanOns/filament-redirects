<?php

namespace VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use VanOns\FilamentRedirects\Filament\Imports\RedirectImporter;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(RedirectImporter::class),
            CreateAction::make(),
        ];
    }
}
