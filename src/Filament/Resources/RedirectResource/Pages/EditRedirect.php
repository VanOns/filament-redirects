<?php

namespace VanOns\FilamentRedirects\Filament\Resources\RedirectResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource;

class EditRedirect extends EditRecord
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
