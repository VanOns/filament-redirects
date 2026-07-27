<?php

namespace VanOns\FilamentRedirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentRedirects\Models\Redirect;

readonly class ValidRegex implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (@preg_match(Redirect::pattern((string) $value), '') !== false) {
            return;
        }

        $message = __('filament-redirects::general.invalid_regex');
        $fail(is_string($message) ? $message : '');
    }
}
