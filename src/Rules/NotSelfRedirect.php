<?php

namespace VanOns\FilamentRedirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

readonly class NotSelfRedirect implements ValidationRule
{
    public function __construct(private ?string $from) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && $value !== '' && rtrim((string) $value, '/') === rtrim((string) $this->from, '/')) {
            $fail((string) __('filament-redirects::general.from_equals_to'));
        }
    }
}
