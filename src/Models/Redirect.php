<?php

namespace VanOns\FilamentRedirects\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
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
 * @property \Carbon\Carbon $last_hit
 * @property bool $active
 * @property int $priority
 * @property Type $type
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
     * @throws BindingResolutionException
     */
    public function createUrl(): string
    {
        $url = $this->handleRedirectType();

        if ($this->include_query) {
            $query = request()->getQueryString();

            if (strlen($query) > 0) {
                $url .= '?'.$query;
            }
        }

        return $url;
    }

    private function handleRedirectType()
    {
        return match ($this->type) {
            Type::Static => $this->to,
            Type::Match => $this->to,
            Type::Replace => str_replace($this->from, $this->to, request()->path()),
            default => config('app.url'),
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

        static::updated(function () {
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
