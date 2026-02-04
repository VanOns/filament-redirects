<?php

namespace VanOns\FilamentRedirects\Filament\Actions;

use Filament\Actions\Action;

class OpenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'open';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->url(fn (?string $state) => url($state ?? ''), true)
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->tooltip(__('filament-redirects::general.open'));
    }
}