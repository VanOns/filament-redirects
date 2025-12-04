# Filament Redirects

Filament-Redirects is a package provides your FilamentPHP app with a resource to
manage your application's redirects in.

## Compatibility

For certain Filament versions, changes have to be made that render the package backwards incompatible with the previous version.
Please see the table below to determine which version you need.

| Version                                                            | Filament |
|--------------------------------------------------------------------|----------|
| v2 (current)                                                       | \>=4.0   |
| [v1](https://github.com/VanOns/filament-redirects/tree/release/v1) | <4.0     |

**Please note:** the `main` branch will always be the latest major version.

## Installation

### Add the package

This package is not yet published on packagist,
therefore you must add it as a repository to your `composer.json` file:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/VanOns/filament-redirects"
    }
],
```

Now you can install the package: `composer require van-ons/filament-redirects`.

Publish the migrations and apply them.
```bash
php artisan vendor:publish --tag=vanons-filament-redirects-migrations
php artisan migrate
```

Optionally, you can publish this package's configuration file.
```bash
php artisan vendor:publish --tag=vanons-filament-redirects-config
```

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
        return $panel->plugin(RedirectsPlugin::make());
    }
}
```

Now the resource is visibile in your admin panel.

## Configuration

### Routing

By default this package uses Laravel's [fallback routes](https://laravel.com/docs/12.x/routing#fallback-routes)
to handle redirects. This package's route is automatically added to your app.

If you would like to disable this, you can turn it off in the config, or set
`FILAMENT_REDIRECTS_ADD_ROUTE` to false in your `.env` file.

To reuse the routing, add `VanOns\FilamentRedirects\Actions\RedirectAction` as a handler
for your route:

```php
Route::get('{parameter}',VanOns\FilamentRedirects\Controllers\RedirectController::class);
```

or use the `VanOns\FilamentRedirects\Actions\RedirectAction` if you want to integrate in a controller:
```php
use VanOns\FilamentRedirects\Actions\RedirectAction;

$redirect = (new RedirectAction)();
if ($redirect) {
    return $redirect;
}

abort(404);
```

### Navigation

You can customize the navigation by customizing the translations:

To adjust the label overwrite
```php
trans_choice('filament-redirects::models/redirect.label', 2)
```

To add a navigation group for the resource, chain the `navigationGroup` method when registering the plugin.
```php
use Filament\Panel;
use Filament\PanelProvider;
use VanOns\FilamentRedirects\RedirectsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->plugin(
            RedirectsPlugin::make()->navigationGroup('Settings')
        );
    }
}
```

## Usage

There are three supported redirect types, in order of priority:

- Static: a static url to a static destination.
- Match: a regular expression to a static destination.
- Replace: Replace a segment of a url with something else.

