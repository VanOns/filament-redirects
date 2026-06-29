# Configuration

## Routing

The `RedirectMiddleware` is automatically registered by the package and runs on every request.
When a matching redirect rule is found, it redirects the visitor. Otherwise, the request passes
through unchanged.

To disable this, set `add_middleware` to `false` in the config file, or add the following to your
`.env` file:

```env
FILAMENT_REDIRECTS_ADD_MIDDLEWARE=false
```

### Fallback route

As an alternative to the middleware, you can use Laravel's
[fallback route](https://laravel.com/docs/routing#fallback-routes). Note that this is **not
recommended** — a fallback route only triggers when no other route matches, meaning requests to
routes that return a 404 themselves will never reach the redirect logic. The middleware runs on
every request, so it catches a much broader set of cases.

To enable the fallback route, set `add_route` to `true` in the config file, or add the following
to your `.env` file:

```env
FILAMENT_REDIRECTS_ADD_ROUTE=true
```

### Manual integration

To integrate the redirect logic manually inside a controller or route, use
`VanOns\FilamentRedirects\Actions\RedirectAction` directly:

```php
use VanOns\FilamentRedirects\Actions\RedirectAction;

$redirect = (new RedirectAction)();

if ($redirect) {
    return $redirect;
}

abort(404);
```

## Navigation

### Label

To adjust the navigation label, overwrite the translation key:

```php
trans_choice('filament-redirects::models/redirect.label', 2)
```

### Navigation group

To add the resource to a navigation group, set `add_nav_group` to `true` in the config file:

```php
// config/filament-redirects.php
'add_nav_group' => true,
```

The group name defaults to the `navigation-group` translation key. To change it, publish and
edit the language files:

```bash
php artisan vendor:publish --tag=vanons-filament-redirects-translations
```
