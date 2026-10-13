# Upgrading

We aim to make upgrading between versions as smooth as possible, but sometimes it involves specific steps to be taken.
This document will outline those steps. And as much as we try to cover all cases, we might miss some. If you come
across such a case, please let us know by [opening an issue](https://github.com/VanOns/filament-redirects/issues), or by adding it yourself and creating a pull request.

## v2.3.0

A static redirect is now looked up by its path instead of loading every active redirect on each request. A new
migration adds the index that lookup uses. Publish and run it:

```bash
php artisan vendor:publish --tag=vanons-filament-redirects-migrations
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
