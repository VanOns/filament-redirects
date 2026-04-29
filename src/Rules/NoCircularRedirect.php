<?php

namespace VanOns\FilamentRedirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Models\Redirect;

readonly class NoCircularRedirect implements ValidationRule
{
    public function __construct(private ?string $from)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $this->from === null || $this->from === '') {
            return;
        }

        $map = Redirect::query()
            ->where('active', true)
            ->where('type', Type::Static)
            ->get(['from', 'to'])
            ->mapWithKeys(fn (Redirect $r) => [rtrim($r->from, '/') => rtrim($r->to ?? '', '/')])
            ->all();

        $target = rtrim($this->from, '/');
        $current = rtrim((string) $value, '/');
        $visited = [];

        while ($current !== '' && ! isset($visited[$current])) {
            if ($current === $target) {
                $message = __('filament-redirects::general.circular_redirect');
                $fail(is_string($message) ? $message : '');

                return;
            }

            $visited[$current] = true;
            $current = $map[$current] ?? '';
        }
    }
}
