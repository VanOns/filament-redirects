<?php

namespace VanOns\FilamentRedirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentRedirects\Models\Redirect;

readonly class NoCircularRedirect implements ValidationRule
{
    private const MAX_HOPS = 10;

    public function __construct(private ?string $from)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $this->from === null || $this->from === '') {
            return;
        }

        $redirects = Redirect::query()
            ->active()
            ->orderBy('priority')
            ->get();

        $target = rtrim($this->from, '/');
        $current = rtrim((string) $value, '/');
        $visited = [];

        // a replace chain can keep growing the path, so cap the walk
        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            if ($current === '' || isset($visited[$current])) {
                return;
            }

            if ($current === $target) {
                $message = __('filament-redirects::general.circular_redirect');
                $fail(is_string($message) ? $message : '');

                return;
            }

            $visited[$current] = true;
            $next = $redirects->first(fn (Redirect $redirect) => $redirect->matches($current));

            if ($next === null) {
                return;
            }

            $current = rtrim((string) $next->destinationFor($current), '/');
        }
    }
}
