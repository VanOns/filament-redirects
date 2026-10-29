# Configuration

## Routing

The `RedirectMiddleware` is automatically registered by the package and runs on every request.
When a matching redirect rule is found, it redirects the visitor. Otherwise, the request passes
through unchanged.

To disable this, set `add_middleware` to `false` in the config file, or add the following to your
`.env` file:

```dotenv
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

```dotenv
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

## Forwarded headers

Each redirect has an "include headers" toggle. When it is enabled, headers from the incoming
request can be copied onto the redirect response, but only the ones you explicitly allow:

```php
'forwarded_headers' => ['X-Request-Id'],
```

The list is empty by default, so no request headers are forwarded unless you name them. Keep it
that way unless a specific header is genuinely needed downstream: redirect responses are
cacheable, and forwarded values belong to a single visitor's request. When at least one header is
forwarded, the response is sent with `Cache-Control: no-store` so it stays out of shared caches.

## Destination checks

The package can check whether the destination of each redirect still works, so a redirect that sends visitors to a
page that is gone does not go unnoticed. The checks request every destination, so they are off by default. Turn them
on in the config file, or add the following to your `.env` file:

```dotenv
FILAMENT_REDIRECTS_CHECKS_ENABLED=true
```

With the checks on, the package schedules the `filament-redirects:check` command every night at 03:00, and the
resource shows the result of the last check for every redirect. A destination ends up as one of:

| Result | When |
|--------|------|
| Works | The destination answers with a `2xx` status, after its own redirects are followed. |
| Blocks checks | The destination answers `401`, `403` or `429`. A login or bot protection refuses the check, while a visitor may well get through, so open the link to see for yourself. |
| Broken | Any other status, a destination that keeps redirecting, or no answer at all. |

A clock means the destination has not been checked yet, a dash that it is not checked automatically. The navigation
badge counts the active redirects with a broken destination. Editors check a single redirect again by clicking its
result in the list, or a selection with the bulk action. Changing the destination of a redirect clears the result of its
last check.

Only a destination that is the same for every visitor can be checked. A `replace` redirect, and a `match` redirect
that fills in `$1` and the like, depend on the visited URL and are skipped.

The command puts a job per redirect on the queue. Run it by hand, for some redirects only, or right away without the
queue:

```bash
php artisan filament-redirects:check
php artisan filament-redirects:check --id=12 --id=14
php artisan filament-redirects:check --sync
```

The options:

```php
'checks' => [
    'enabled' => env('FILAMENT_REDIRECTS_CHECKS_ENABLED', false),
    'schedule' => '0 3 * * *',
    'queue' => null,
    'timeout' => 10,
    'base_url' => null,
    'user_agent' => 'Mozilla/5.0 (compatible; FilamentRedirects; +https://github.com/VanOns/filament-redirects)',
],
```

- `schedule`: when the checks run, as a cron expression. Set it to `null` to schedule the command yourself.
- `queue`: the queue the jobs go on, the default queue when `null`.
- `timeout`: seconds to wait for a destination to answer.
- `base_url`: a destination on this site, such as `blog/new`, is requested on `app.url`. Set this when the server
  cannot reach its own public URL, for example `http://localhost`. A site behind basic auth or an IP allowlist answers
  the check with `401` or `403`, so its own pages show up as "Blocks checks".
- `user_agent`: the user agent the checks send. A request with this user agent does not count as a hit.

### Reacting to a broken destination

When a check finds a destination broken, the `VanOns\FilamentRedirects\Events\DestinationBroken` event is
dispatched. It fires once, and again only after the destination has worked in between. Listen to it to send a mail or a
Slack message:

```php
use Illuminate\Support\Facades\Event;
use VanOns\FilamentRedirects\Events\DestinationBroken;

Event::listen(function (DestinationBroken $event) {
    // $event->redirect
});
```

## Navigation

### Label

To adjust the navigation label, overwrite the translation key:

```php
trans_choice('filament-redirects::models/redirect.label', 2)
```

### Navigation group

To add the resource to a navigation group, chain the `navigationGroup` method when registering
the plugin:

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

## Using a different base URL

If you want to use the package to manage redirect for a different application, you will need should set the base URL for
this host.
This will ensure the redirect buttons & checks refer to the correct URLs.

To do so, simple set `FILAMENT_REDIRECTS_BASE_URL` to the base URL of the application that will use the redirects:

```dotenv
FILAMENT_REDIRECTS_BASE_URL=https://other-website.com
```
