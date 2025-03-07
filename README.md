# Filament Redirects

Filament-Redirects is a package provides your FilamentPHP app with a resource to
manage your application's redirects in.

This package dependes on [van-ons/laravel-redirector](https://github.com/VanOns/laravel-redirector)
for the implementation of redirecting.

## Installation

### Add the package

This package and `van-ons/laravel-redirector` are not yet published on packagist,
therefore you must add them as repositories to your `composer.json` file:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/filament-redirects"
    },
    {
        "type": "vcs",
        "url": "https://github.com/VanOns/laravel-redirector"
    }
],
```

Now you can install the package: `composer require van-ons/filament-redirects`.

Publish the migrations with `php artisan vendor:publish --tag=vanons-redirector-migrations`,
and apply them `php artisan migrate`.

Optionally, you can publish this package's configuration file with:
`php artisan vendor:publish --tag=vanons-filament-redirects`.

### Install Filament Plugin

Add the plugin to your app FilamentPHP serviceprovider:

```php
use Filament\Panel;
use Filament\PanelProvider;
use VanOns\FilamentRedirects\RedirectsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugin(RedirectsPlugin::make());
    }
}
```

Now the resource is visibile in your admin panel.

## Configuration

By default this package uses Laravel's [fallback routes](https://laravel.com/docs/12.x/routing#fallback-routes)
to handle redirects. This package's route is automatically added to your app.

If you would like to disable this, you can turn it off in the config, or set
`FILAMENT_REDIRECTS_ADD_ROUTE` to false in your `.env` file.

To reuse the routing, add `VanOns\Redirector\Actions\RedirectAction` as a handler
for your route:

```php
Route::get('{parameter}',VanOns\Redirector\Actions\RedirectAction::class);
```

## Notes

This app only redirects on the first url segment, it is only possible to create
redirects for the 'possible' part in `https://app.test/possible/impossible/impossible`.

If a redirect exists for `https://app.test/possible`, the app will redirect the user.
