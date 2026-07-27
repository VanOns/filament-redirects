<?php

namespace VanOns\FilamentRedirects\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use VanOns\FilamentRedirects\Enums\Keys;
use VanOns\FilamentRedirects\Enums\Type;

/**
 * @property int $id
 * @property string $from
 * @property string $to
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
            Type::Match => $this->matchesPattern($path),
            Type::Replace => str_contains($path, $this->from),
        };
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
            $query = request()->getQueryString();

            if (!empty($query)) {
                $url .= '?'.$query;
            }
        }

        return $url ?? url('/');
    }

    public function destinationFor(string $path): ?string
    {
        return match ($this->type) {
            Type::Static, Type::Match => $this->to,
            Type::Replace => str_replace($this->from, $this->to, $path),
        };
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
