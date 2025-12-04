<?php

namespace VanOns\FilamentRedirects;

use Filament\Contracts\Plugin;
use Filament\Panel;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource;

class RedirectsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'redirects';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([RedirectResource::class]);
    }

    public function boot(Panel $panel): void
    {
        // Do nothing
    }

    public function navigationGroup(?string $navigationGroup): static
    {
        RedirectResource::navigationGroup($navigationGroup);

        return $this;
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
