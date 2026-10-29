## Filament Redirects (`van-ons/filament-redirects`)

Manages HTTP redirects through a Filament resource. Ships an Eloquent-backed `redirects` table, a Filament resource for CRUD, a `RedirectMiddleware` that runs on every request, and a `RedirectAction` you can invoke manually. Three redirect types: `static` (exact path), `match` (regex), `replace` (substring swap). Service provider is auto-registered.

### Setup

@verbatim
<code-snippet name="Install + publish migrations" lang="bash">
composer require van-ons/filament-redirects
php artisan vendor:publish --tag=vanons-filament-redirects-migrations
php artisan migrate
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Register the Filament plugin" lang="php">
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
</code-snippet>
@endverbatim

### Public API

| Class | Purpose |
|-------|---------|
| `VanOns\FilamentRedirects\RedirectsPlugin` | Filament plugin. `::make()`, `->navigationGroup(?string)` |
| `VanOns\FilamentRedirects\Models\Redirect` | Eloquent model. Fields: `from`, `to`, `type`, `status_code`, `include_headers`, `include_query`, `priority`, `hits`, `last_hit`, `active`. Soft-deletes. Has `active()` scope. |
| `VanOns\FilamentRedirects\Enums\Type` | `Type::Static`, `Type::Match`, `Type::Replace` |
| `VanOns\FilamentRedirects\Actions\RedirectAction` | Invokable. `(new RedirectAction)()` returns `RedirectResponse\|Redirector\|null`. Optional ctor args: `?string $path, ?array $headers, ?Request $request`. |
| `VanOns\FilamentRedirects\Middleware\RedirectMiddleware` | Pushed to the global stack when `add_middleware=true`. |
| `VanOns\FilamentRedirects\Controllers\RedirectController` | Invokable controller wrapping `RedirectAction`; `abort(404)` on no match. |
| `VanOns\FilamentRedirects\Rules\NotSelfRedirect` | Validation rule. Ctor: `?string $from`. Apply to the `to` field. |
| `VanOns\FilamentRedirects\Rules\NoCircularRedirect` | Validation rule. Walks active `static` redirects to detect cycles. Apply to `to`. |
| `VanOns\FilamentRedirects\Filament\Imports\RedirectImporter` | Filament Importer used by the resource's `ImportAction`. |
| `VanOns\FilamentRedirects\Filament\Actions\OpenAction` | Filament action that opens a path in a new tab; reads the field's state for the URL. |
| `VanOns\FilamentRedirects\Enums\Keys` | `Keys::Cache` → `redirector-models` (cache key for the match and replace redirects). |
| `VanOns\FilamentRedirects\Enums\DestinationHealth` | Result of a destination check: `Healthy` (2xx), `Blocked` (401/403/429), `Broken` (anything else, or no answer). Stored on `Redirect::$destination_health`, null when not checked. |
| `VanOns\FilamentRedirects\Actions\CheckDestinationAction` | Invokable. `(new CheckDestinationAction($redirect))()` requests the destination, stores the result and returns the `DestinationHealth`, or null when the destination depends on the visited URL. |
| `VanOns\FilamentRedirects\Jobs\CheckDestination` | Queued, unique per redirect. Runs `CheckDestinationAction`. |
| `VanOns\FilamentRedirects\Events\DestinationBroken` | Dispatched when a check finds a destination broken, once until it works again. Carries `$redirect`. |
| `filament-redirects:check` | Artisan command. Queues a check per active checkable redirect. `--id=*` to limit, `--sync` to check right away. |

### Config (`config/filament-redirects.php`)

| Key | Default | Notes |
|-----|---------|-------|
| `add_middleware` | `true` | Auto-pushes `RedirectMiddleware` to the HTTP kernel. Env: `FILAMENT_REDIRECTS_ADD_MIDDLEWARE`. |
| `add_route` | `false` | Registers a `Route::fallback(RedirectAction::class)`. Env: `FILAMENT_REDIRECTS_ADD_ROUTE`. |
| `status_codes` | `[301, 302, 303, 307, 308]` | Allowed values in the form select + importer. |
| `default_status_code` | `301` | Default selected in the form (DB default is set in the migration). |
| `cache.enabled` | `false` | Caches the active match and replace redirects; static redirects are looked up by path. |
| `cache.ttl` | `60` | Seconds. Auto-invalidated on model `created/updated/deleted/restored`. |
| `checks.enabled` | `false` | Destination checks: schedules the command and shows the results in the resource. Env: `FILAMENT_REDIRECTS_CHECKS_ENABLED`. |
| `checks.schedule` | `'0 3 * * *'` | Cron expression. `null` to schedule `filament-redirects:check` yourself. |
| `checks.queue` | `null` | Queue for the check jobs. |
| `checks.timeout` | `10` | Seconds per request. |
| `checks.base_url` | `null` | Host for checking relative destinations instead of `app.url` or `filament-redirects.base_url`. |
| `checks.user_agent` | `Mozilla/5.0 (compatible; FilamentRedirects; …)` | Sent by the checks. Requests with it do not count as a hit. |
| `base_url` | `null` | Host for relative destinations instead of `app.url`. |

Publish tags: `vanons-filament-redirects-migrations`, `vanons-filament-redirects-config`, `vanons-filament-redirects-translations`.

### Conventions

- **Default to the middleware**, not `add_route`. The fallback route only fires when no other route matches; the middleware runs on every request and catches existing-but-404 routes.
- For manual integration in a controller/route, invoke `RedirectAction` directly — do not re-implement type matching:
  @verbatim
  <code-snippet name="Manual integration" lang="php">
  use VanOns\FilamentRedirects\Actions\RedirectAction;

  $redirect = (new RedirectAction)();
  if ($redirect) {
      return $redirect;
  }
  abort(404);
  </code-snippet>
  @endverbatim
- Trim leading/trailing slashes when writing `from` and `to` — that's what the importer and the form do (`str_contains`/regex matching is path-relative, no leading slash).
- When building custom forms that write to the `Redirect` model, apply both `NotSelfRedirect` and `NoCircularRedirect` to the `to` field, passing the current `from` value.
- The `priority` column is auto-set on create (`max(priority) + 1`) when null. Reorder via the resource's drag handle, or set explicitly.
- Don't rename or override the `redirects` table without also overriding `Redirect::$table` — the action queries it directly.
- When importing many redirects programmatically with `cache.enabled=true`, prefer creating in a single batch then `Cache::forget(Keys::Cache->value)` once, rather than one-by-one.
- Only `static` redirects and `match` redirects without `$1` in `to` are checked. Use `Redirect::checkableDestination()` to get the URL a check requests, rather than building it yourself.
- To notify someone about a broken destination, listen to `DestinationBroken`. Do not poll the `destination_health` column.
- Override the resource label by translating `filament-redirects::models/redirect.label` (singular/plural via `trans_choice`).
