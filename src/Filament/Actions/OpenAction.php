<?php

namespace VanOns\FilamentRedirects\Filament\Actions;

use Filament\Forms\Components\Actions\Action;

class OpenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'open';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->icon('heroicon-o-arrow-top-right-on-square')
            ->tooltip(__('filament-redirects::general.open'))
            ->url(function ($livewire, ?string $state) {
                return url($state ?: '/');
            }, true);
    }
}
