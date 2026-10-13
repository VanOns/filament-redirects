<?php

namespace VanOns\FilamentRedirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VanOns\FilamentRedirects\Models\Redirect;

readonly class ValidGroupReferences implements ValidationRule
{
    public function __construct(private ?string $from)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $this->from === null || $this->from === '') {
            return;
        }

        // the empty alternative always matches, and then every group of the pattern is reported, so they can be counted
        if (@preg_match(Redirect::pattern("(?:{$this->from})|"), '', $groups, PREG_UNMATCHED_AS_NULL) !== 1) {
            return;
        }

        $groupCount = count(array_filter(array_keys($groups), is_int(...))) - 1;

        preg_match_all(Redirect::GROUP_REFERENCE, (string) $value, $references, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($references as $reference) {
            $group = (int) ($reference[1] ?? $reference[2]);

            if ($group > $groupCount) {
                $message = __('filament-redirects::general.unknown_group_reference', ['reference' => $reference[0]]);
                $fail(is_string($message) ? $message : '');

                return;
            }
        }
    }
}
