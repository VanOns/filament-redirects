<?php

namespace VanOns\FilamentRedirects\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use VanOns\FilamentRedirects\Enums\Keys;
use VanOns\FilamentRedirects\Enums\Type;

/**
 * @property int $id
 * @property string $from
 * @property string|null $to
 * @property int $status_code
 * @property bool $include_headers
 * @property bool $include_query
 * @property int $hits
 * @property Carbon $last_hit
 * @property bool $active
 * @property int|null $priority
 * @property Type $type
 * @property string|null $category
 * @property string|null $title
 */
class Redirect extends Model
{
    use SoftDeletes;

    /**
     * A `$1` or `${1}` in the destination of a `match` redirect.
     */
    public const GROUP_REFERENCE = '/\$(?:(\d+)|\{(\d+)\})/';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'hits' => 'integer',
            'include_headers' => 'boolean',
            'include_query' => 'boolean',
            'last_hit' => 'datetime',
            'priority' => 'integer',
            'status_code' => 'integer',
            'type' => Type::class,
        ];
    }

    public function hit(): bool
    {
        return $this->update([
            'hits' => ++$this->hits,
            'last_hit' => now(),
        ]);
    }

    /**
     * @param  Builder<Redirect>  $query
     * @return Builder<Redirect>
     *
     * @throws InvalidArgumentException
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', '=', true);
    }

    /**
     * Build the PCRE pattern for a `match` redirect. Only the delimiter is escaped, the rest of
     * the stored value stays the regex the admin wrote.
     */
    public static function pattern(string $from): string
    {
        return '#'.str_replace('#', '\#', $from).'#';
    }

    public function matches(string $path): bool
    {
        return match ($this->type) {
            Type::Static => $this->from === $path,
            Type::Match => $this->matchesPattern($path) && $this->staysWithinItsDestination($path),
            Type::Replace => str_contains($path, $this->from) && $this->staysWithinItsDestination($path),
        };
    }

    /**
     * Whether the destination for the path leaves this site only for the host its `to` names. Part of a match or
     * replace destination comes from the request, so `^nl/(.*)$` to `$1` must not send `/nl/https://evil.test` off-site.
     */
    private function staysWithinItsDestination(string $path): bool
    {
        $origin = self::origin((string) $this->destinationFor($path));

        return $origin === null || $origin === self::origin((string) preg_replace(self::GROUP_REFERENCE, '', (string) $this->to));
    }

    /**
     * The scheme and host a destination leaves this site for, or null for a path on this site, read the way the
     * redirector reads it.
     */
    private static function origin(string $url): ?string
    {
        if (!app(UrlGenerator::class)->isValidUrl($url)) {
            return null;
        }

        $parts = parse_url($url) ?: [];

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : ''));
    }

    private function matchesPattern(string $path): bool
    {
        $result = @preg_match(self::pattern($this->from), $path);

        // a pattern that does not compile is skipped, the other redirects still get evaluated
        if ($result === false) {
            Log::warning('Redirect has an invalid regular expression.', [
                'redirect' => $this->id,
                'error' => preg_last_error_msg(),
            ]);

            return false;
        }

        return $result === 1;
    }

    /**
     * @throws BindingResolutionException
     */
    public function createUrl(?string $path = null): ?string
    {
        $url = $this->destinationFor($path ?? request()->path());

        if ($this->include_query) {
            $url = self::withQuery($url, request()->getQueryString());
        }

        return $url ?? url('/');
    }

    public function destinationFor(string $path): ?string
    {
        return match ($this->type) {
            Type::Static => $this->to,
            Type::Match => $this->fillCapturedGroups($path),
            Type::Replace => str_replace($this->from, (string) $this->to, $path),
        };
    }

    /**
     * Fill `$1` or `${1}` in the destination with what that group of the pattern captured.
     */
    private function fillCapturedGroups(string $path): ?string
    {
        if ($this->to === null || @preg_match(self::pattern($this->from), $path, $groups, PREG_UNMATCHED_AS_NULL) !== 1) {
            return $this->to;
        }

        // a reference past the groups of the pattern stays as written, so a destination with a literal `$5` keeps working
        return preg_replace_callback(
            self::GROUP_REFERENCE,
            function (array $reference) use ($groups): string {
                $group = (int) ($reference[1] ?? $reference[2]);

                return array_key_exists($group, $groups) ? (string) $groups[$group] : (string) $reference[0];
            },
            $this->to,
            flags: PREG_UNMATCHED_AS_NULL,
        );
    }

    // The query goes in front of a fragment, or the browser reads it as part of the fragment.
    private static function withQuery(?string $url, ?string $query): ?string
    {
        if (empty($query)) {
            return $url;
        }

        $url = (string) $url;
        $fragment = str_contains($url, '#') ? '#'.Str::after($url, '#') : '';
        $url = Str::before($url, '#');

        return $url.(str_contains($url, '?') ? '&' : '?').$query.$fragment;
    }

    protected static function booted(): void
    {
        static::creating(function (Redirect $redirect) {
            if ($redirect->priority === null) {
                $redirect->priority = Redirect::query()->max('priority') + 1;
            }
        });

        static::created(function () {
            Cache::forget(Keys::Cache->value);
        });

        static::updated(function (Redirect $redirect) {
            $changed = array_diff(array_keys($redirect->getChanges()), ['hits', 'last_hit', 'updated_at']);
            if (empty($changed)) {
                return;
            }

            Cache::forget(Keys::Cache->value);
        });

        static::deleted(function () {
            Cache::forget(Keys::Cache->value);
        });

        static::restored(function () {
            Cache::forget(Keys::Cache->value);
        });
    }
}
