---
name: filament-redirects-integration
description: Use when the user wants to create, query, or manage filament-redirects records programmatically (seeders, jobs, services), invoke RedirectAction manually in a controller/route, build a custom form that writes to the Redirect model, prepare a CSV for the importer, or tune the redirect cache. Not needed for plain CRUD through the Filament UI.
---

# Filament Redirects: programmatic integration

## When to use this skill

Load this skill when the user is doing one of:

- Creating `Redirect` records from code (seeder, command, service, job).
- Calling `RedirectAction` directly inside a controller, route handler, or custom middleware.
- Building a custom form/livewire component that persists `Redirect` rows.
- Preparing a CSV for the built-in importer, or wiring up imports programmatically.
- Enabling/tuning the redirect cache, or invalidating it after bulk changes.
- Disabling the bundled middleware in favor of manual matching.

Skip this skill for plain admin-panel CRUD through the shipped Filament resource — that needs no extra wiring.

## Model reference

`VanOns\FilamentRedirects\Models\Redirect` — `redirects` table, soft-deleted, `$guarded = ['id']` (mass-assignable).

| Column | Type | Required | Notes |
|--------|------|----------|-------|
| `from` | string(255) | yes | Path without leading slash. For `match`, this is a regex without delimiters. For `replace`, a literal substring. |
| `to` | string(255) | nullable | Destination path. For `match`/`replace` semantics see Type table below. |
| `type` | `Type` enum | yes | `Type::Static` / `Type::Match` / `Type::Replace`. |
| `category` | string(255) | nullable | Optional tag used to group/filter redirects in the resource. Not used in matching. |
| `title` | string(255) | nullable | Optional human-readable label for the redirect. Not used in matching. |
| `status_code` | int | yes (default 301) | Must be one of `config('filament-redirects.status_codes')`. |
| `include_headers` | bool | yes (default true) | Forwards original request headers on redirect. |
| `include_query` | bool | yes (default true) | Appends current query string to the destination. |
| `active` | bool | yes (default true) | Only `active=true` rows are evaluated. |
| `priority` | int | nullable | Lower is checked first. Auto-set to `max(priority)+1` on create when null. |
| `hits` | int | (read-only) | Incremented by `Redirect::hit()` whenever the row matches a request. |
| `last_hit` | timestamp | (read-only) | Set alongside `hits`. |

Scope: `Redirect::active()` filters `where('active', true)`.

## Type semantics

| Type | Matching | Destination | Example |
|------|----------|-------------|---------|
| `Type::Static` | `$from === request()->path()` (exact) | Literal `to` | `from='old-page'`, `to='new-page'` → `/old-page` → `/new-page` |
| `Type::Match` | `preg_match('/<from>/', request()->path())` (forward slashes auto-escaped, no delimiters in `from`) | Literal `to` (no capture-group interpolation) | `from='blog/[0-9]+/.*'`, `to='blog'` → `/blog/123/x` → `/blog` |
| `Type::Replace` | Substring check via `str_contains` | `str_replace($from, $to, request()->path())` | `from='en/blog'`, `to='nl/blog'` → `/en/blog/x` → `/nl/blog/x` |

Always strip leading/trailing slashes from both `from` and `to` — match logic is path-relative.

## Creating redirects programmatically

```php
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Models\Redirect;

Redirect::create([
    'from' => 'old-page',
    'to' => 'new-page',
    'type' => Type::Static,
    'status_code' => 301,
    'include_headers' => true,
    'include_query' => true,
    'active' => true,
    // 'priority' => null → auto-assigned to max+1
]);
```

Bulk seed:

```php
Redirect::insert([
    ['from' => 'a', 'to' => 'b', 'type' => 'static', 'status_code' => 301, 'include_headers' => 1, 'include_query' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
    // ...
]);

// insert() bypasses model events → manually invalidate the cache:
\Illuminate\Support\Facades\Cache::forget(\VanOns\FilamentRedirects\Enums\Keys::Cache->value);
```

## Manual invocation (no middleware)

Use when `add_middleware=false` and you want redirects only on specific routes/controllers.

```php
use VanOns\FilamentRedirects\Actions\RedirectAction;

Route::get('/{any}', function () {
    if ($redirect = (new RedirectAction)()) {
        return $redirect;
    }
    abort(404);
})->where('any', '.*');
```

Or use the shipped controller directly:

```php
use VanOns\FilamentRedirects\Controllers\RedirectController;

Route::fallback(RedirectController::class); // 404s when no match
```

`RedirectAction::__invoke()` returns `RedirectResponse|Redirector|null`. `null` means no rule matched — caller decides what to do next.

Optional ctor args override what's read from the current request:

```php
new RedirectAction(
    path: 'some/other/path',
    headers: $customHeaders,
    request: $request,
);
```

## Custom forms that write `Redirect`

Apply both validation rules to the `to` field, passing the current `from`:

```php
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use VanOns\FilamentRedirects\Rules\NoCircularRedirect;
use VanOns\FilamentRedirects\Rules\NotSelfRedirect;

TextInput::make('from')->reactive()->required(),
TextInput::make('to')
    ->rule(fn (Get $get) => new NotSelfRedirect($get('from')))
    ->rule(fn (Get $get) => new NoCircularRedirect($get('from')))
    ->required(),
```

`NoCircularRedirect` only walks **active static** redirects (it cannot detect cycles through `match`/`replace` rules, by design — those types don't form a deterministic graph).

## CSV importer format

The shipped `RedirectImporter` (used by the list-page Import button) requires these columns:

| Column | Required | Validation |
|--------|----------|------------|
| `from` | yes | string, max 255. Trimmed of `/`. |
| `to` | nullable* | string, max 255. Trimmed of `/`. (`*` declared `nullable` in importer rules but `beforeSave` runs `NotSelfRedirect`/`NoCircularRedirect` against it.) |
| `type` | yes | `static` / `match` / `replace` |
| `status_code` | yes | one of `301,302,303,307,308` |
| `include_headers` | yes | boolean (`true`/`false`/`1`/`0`) |
| `include_query` | yes | boolean (`true`/`false`/`1`/`0`) |
| `category` | no | string, max 255. Trimmed. |
| `title` | no | string, max 255. Trimmed. |

Imports run on the queue. For non-`sync` queues, the user needs Laravel's notifications table:

```bash
php artisan make:notifications-table
php artisan migrate
```

To attach the importer to a custom Filament page:

```php
use Filament\Actions\ImportAction;
use VanOns\FilamentRedirects\Filament\Imports\RedirectImporter;

ImportAction::make()->importer(RedirectImporter::class);
```

## Cache

Disabled by default. Enable in `config/filament-redirects.php`:

```php
'cache' => [
    'enabled' => true,
    'ttl' => 60, // seconds
],
```

When enabled, `RedirectAction` reads through `Cache::remember(Keys::Cache->value, ttl, ...)`. The `Redirect` model auto-invalidates on `created/updated/deleted/restored`. Manual invalidation:

```php
use Illuminate\Support\Facades\Cache;
use VanOns\FilamentRedirects\Enums\Keys;

Cache::forget(Keys::Cache->value); // 'redirector-models'
```

You **must** invalidate manually after `DB::table('redirects')->...` or `Redirect::insert(...)` — those bypass model events.

## Anti-patterns

- **Don't** prepend leading slashes to `from`/`to`. The action compares against `request()->path()` which has none, and the importer/form trim them.
- **Don't** include regex delimiters in a `match` `from`. The action wraps the value in `/.../` itself and escapes forward slashes.
- **Don't** put captured-group references (`$1`, `\1`) in `to` for `match` redirects — they're returned as literal strings; use `replace` if you need substitution.
- **Don't** invoke `RedirectAction` and the bundled middleware simultaneously — set `add_middleware=false` if you're handling matching manually.
- **Don't** disable `add_middleware` and `add_route` together while expecting redirects to work — at least one path (middleware, fallback route, or your own manual call) must invoke `RedirectAction`.
- **Don't** trust `NoCircularRedirect` for non-static cycles — it only inspects `Type::Static` rows.

## Common follow-ups

- Adding more allowed status codes → append to `config('filament-redirects.status_codes')` and add matching translations under `filament-redirects::general.status_codes.{code}`.
- Changing the navigation group → `RedirectsPlugin::make()->navigationGroup('Settings')` on the panel provider.
- Hiding the resource from non-admins → wrap the plugin call behind your panel's auth/role check, or override `RedirectResource::canViewAny()` via a class extension.
- Replacing the resource entirely → register your own `Resource` and skip `RedirectsPlugin` (or replace it). The model and `RedirectAction` work standalone.
