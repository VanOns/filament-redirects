# Upgrading

We aim to make upgrading between versions as smooth as possible, but sometimes it involves specific steps to be taken.
This document will outline those steps. And as much as we try to cover all cases, we might miss some. If you come
across such a case, please let us know by [opening an issue](https://github.com/VanOns/filament-redirects/issues), or by adding it yourself and creating a pull request.

## v2.7.0

Publishing the migrations again now only adds the ones the app does not have yet. Before, it published every
migration again under a new date, after which `php artisan migrate` failed on a table that already existed. If that
left duplicates in `database/migrations`, delete the copies that were never run (`php artisan migrate:status` shows
them as pending).

The publish tags lost their `vanons-` prefix, and the translations tag is now called `-lang`:

| Before | Now |
|---|---|
| `vanons-filament-redirects-migrations` | `filament-redirects-migrations` |
| `vanons-filament-redirects-config` | `filament-redirects-config` |
| `vanons-filament-redirects-translations` | `filament-redirects-lang` |

The old tags keep working until v3.

## v2.5.0

Redirects can now have their destination checked. A new migration adds the columns for the result. Publish and run it:

```bash
php artisan vendor:publish --tag=filament-redirects-migrations
php artisan migrate
```

The checks are off by default. See [Destination checks](docs/configuration.md#destination-checks) to turn them on.

## v2.3.0

A static redirect is now looked up by its path instead of loading every active redirect on each request. A new
migration adds the index that lookup uses. Publish and run it:

```bash
php artisan vendor:publish --tag=filament-redirects-migrations
php artisan migrate
```

With `cache.enabled`, the cache now holds only the match and replace redirects. Static redirects are always read from
the database, so they take effect without clearing the cache.

<!-- EXAMPLE -->
<!--
## v1 to v2

* Remove the `foo` column from the `bar` table.
* Add the `baz` column to the `bar` table.
* Run `php artisan migrate` to update the database.
-->
