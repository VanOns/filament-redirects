<p align="center"><img src="art/social-card.png" alt="Social card of Filament Redirects"></p>

# Filament Redirects

[![Tests](https://github.com/VanOns/filament-redirects/actions/workflows/run-tests.yml/badge.svg)](https://github.com/VanOns/filament-redirects/actions/workflows/run-tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/van-ons/filament-redirects.svg)](https://packagist.org/packages/van-ons/filament-redirects)
[![License](https://img.shields.io/packagist/l/van-ons/filament-redirects.svg)](LICENSE.md)

Filament Redirects is a package that provides your FilamentPHP app with a resource to
manage URL redirects.

## Compatibility

For certain Filament versions, changes have to be made that render the package backwards incompatible with the previous version.
Please see the table below to determine which version you need.

| Version                                                            | Filament          |
|--------------------------------------------------------------------|-------------------|
| v2 (current)                                                       | \>=4.0 \|  \>=5.0 |
| [v1](https://github.com/VanOns/filament-redirects/tree/release/v1) | <4.0              |

**Please note:** the `main` branch will always be the latest major version.

## Installation

### Add the package

Install the package via Composer:

```bash
composer require van-ons/filament-redirects
```

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

Now the resource is visible in your admin panel.

## Configuration

### Routing

The `RedirectMiddleware` is automatically registered by the package and runs on every request.
When a matching redirect rule is found it redirects the visitor, otherwise the request passes
through unchanged.

If you would like to disable this, set `add_middleware` to `false` in the config, or add
`FILAMENT_REDIRECTS_ADD_MIDDLEWARE=false` to your `.env` file.

Alternatively, you can use Laravel's [fallback route](https://laravel.com/docs/12.x/routing#fallback-routes)
instead of the middleware. However, this is **not recommended** — a fallback route only triggers when
no other route matches, meaning requests to existing but unresolved routes (e.g. a route that returns a
404 itself) will never reach the redirect logic. The middleware runs on every request regardless, so it
catches a much broader set of cases.

To enable the fallback route, set `add_route` to `true` in the config, or add
`FILAMENT_REDIRECTS_ADD_ROUTE=true` to your `.env` file.

If you want to integrate the redirect logic manually inside a controller or route, you can use
`VanOns\FilamentRedirects\Actions\RedirectAction` directly:

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

There are three supported redirect types, evaluated in priority order:

### Static

An exact URL match. The `from` value is compared literally against the current request path.
If it matches, the visitor is redirected to the `to` value.

| Field  | Value            |
|--------|------------------|
| `from` | `old-page`       |
| `to`   | `new-page`       |

A request to `/old-page` will redirect to `/new-page`.

---

### Match

The `from` value is used as a regular expression (PCRE, without delimiters) and tested against
the current request path. If it matches, the visitor is redirected to the static `to` value.
The destination is always a fixed URL — captured groups are not interpolated.

| Field  | Value                  |
|--------|------------------------|
| `from` | `blog/[0-9]+/.*`       |
| `to`   | `blog`                 |

A request to `/blog/123/my-old-post` matches the pattern and redirects to `/blog`.

---

### Replace

The `from` value is matched as a literal substring of the request path using `str_contains`.
If it is found, `str_replace` is used to swap the `from` segment with the `to` segment in the
current path, and the visitor is redirected to the resulting URL.

| Field  | Value       |
|--------|-------------|
| `from` | `en/blog`   |
| `to`   | `nl/blog`   |

A request to `/en/blog/my-post` will redirect to `/nl/blog/my-post`.

## Importing

The package includes a built-in CSV importer powered by [Filament's Import Action](https://filamentphp.com/docs/4.x/actions/import).
You can find the import button in the top-right corner of the redirects resource list.

### CSV columns

The following columns are supported in the CSV file:

| Column            | Required | Values                            | Description                                                               |
|-------------------|----------|-----------------------------------|---------------------------------------------------------------------------|
| `from`            | yes      | string (max 255)                  | The source path (leading/trailing slashes are trimmed automatically)      |
| `to`              | yes      | string (max 255)                  | The destination path (leading/trailing slashes are trimmed automatically) |
| `type`            | yes      | `static`, `match`, `replace`      | The redirect type                                                         |
| `status_code`     | yes      | `301`, `302`, `303`, `307`, `308` | The HTTP status code to use for the redirect                              |
| `include_headers` | yes      | `true`/`false` (`1`/`0`)          | Whether to forward the original request headers                           |
| `include_query`   | yes      | `true`/`false` (`1`/`0`)          | Whether to forward the original query string                              |
| `category`        | no       | string (max 255)                  | Optional category used to group and filter redirects                      |
| `title`           | no       | string (max 255)                  | Optional human-readable label giving the redirect extra context           |

### Queue & notifications table

Imports are processed using Laravel queues. When your queue connection is not `sync`, they are
processed in the background. When an import finishes, Filament sends a database notification to
the user who triggered it. For this to work, your application must have the **notifications table**
present in the database.

If you haven't created it yet, run:

```bash
php artisan make:notifications-table
php artisan migrate
```

> **Note:** Without the notifications table, completed import notifications will fail and you
> will not be informed when the import has finished. See the
> [Filament import documentation](https://filamentphp.com/docs/4.x/actions/import) for more details.

---

<p align="center"><a href="https://van-ons.nl/" target="_blank"><img src="https://opensource.van-ons.nl/files/cow.png" width="50" alt="Logo of Van Ons"></a></p>

