# Installation

## Requirements

- PHP ^8.2
- Laravel ^11.0 | ^12.0 | ^13.0
- Filament ^4.0 | ^5.0

## Install the package

```bash
composer require van-ons/filament-redirects
```

## Publish and run migrations

```bash
php artisan vendor:publish --tag=vanons-filament-redirects-migrations
php artisan migrate
```

## Register the plugin

Add the plugin to your Filament panel provider:

```php
use Filament\Panel;
use Filament\PanelProvider;
use VanOns\FilamentRedirects\RedirectsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->plugin(RedirectsPlugin::make());
    }
}
```

## Optional: publish config

```bash
php artisan vendor:publish --tag=vanons-filament-redirects-config
```
