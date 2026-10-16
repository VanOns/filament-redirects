<?php

namespace VanOns\FilamentRedirects\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DestinationHealth: string implements HasColor, HasIcon, HasLabel
{
    case Healthy = 'healthy';
    case Blocked = 'blocked';
    case Broken = 'broken';

    /**
     * The health of a destination that answered with the given status, after its own redirects were followed.
     */
    public static function fromStatus(int $status): self
    {
        return match (true) {
            $status >= 200 && $status < 300 => self::Healthy,
            // a login or bot protection refuses the check, while a visitor may well get through
            in_array($status, [401, 403, 429], true) => self::Blocked,
            default => self::Broken,
        };
    }

    public function getLabel(): string
    {
        return (string) __("filament-redirects::general.destination_health.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Blocked => 'warning',
            self::Broken => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Healthy => 'heroicon-m-check',
            self::Blocked => 'heroicon-m-shield-exclamation',
            self::Broken => 'heroicon-m-x-circle',
        };
    }
}
