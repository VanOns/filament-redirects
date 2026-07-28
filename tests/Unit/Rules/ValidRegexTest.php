<?php

use VanOns\FilamentRedirects\Rules\ValidRegex;

function validateRegex(mixed $value): bool
{
    $failed = false;
    (new ValidRegex())->validate('from', $value, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

it('passes on a valid pattern', function () {
    expect(validateRegex('blog/[0-9]+/.*'))->toBeFalse();
});

it('passes on a pattern containing the delimiter', function () {
    expect(validateRegex('page#anchor'))->toBeFalse();
});

it('fails on a pattern that does not compile', function () {
    expect(validateRegex('(unclosed'))->toBeTrue();
});

it('passes when value is empty', function () {
    expect(validateRegex(''))->toBeFalse();
});

it('passes when value is null', function () {
    expect(validateRegex(null))->toBeFalse();
});
