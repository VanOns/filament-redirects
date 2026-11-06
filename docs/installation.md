# Installation

Start by installing the package via Composer:

```bash
composer require van-ons/filament-redirects:^2.0
```

Next, publish and run the migrations:

```sh
php artisan vendor:publish --tag=filament-redirects-migrations
php artisan migrate
```

After an update, publish the migrations again; only the new ones are added. `php artisan migrate` warns about
migrations of this package that are not published yet.

Finally, add the plugin to your Filament panel provider:

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

If needed, you can also publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-redirects-config
```
