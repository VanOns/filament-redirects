<?php

use VanOns\FilamentRedirects\Rules\ValidGroupReferences;

function validateGroupReferences(?string $from, mixed $to): bool
{
    $failed = false;
    (new ValidGroupReferences($from))->validate('to', $to, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

it('passes when every reference has a group in the pattern', function () {
    expect(validateGroupReferences('^shop/(\d+)/(\w+)$', 'products/$1/${2}0'))->toBeFalse();
});

it('fails on a reference past the groups of the pattern', function () {
    expect(validateGroupReferences('^shop/(\d+)$', 'products/$2'))->toBeTrue()
        ->and(validateGroupReferences('^shop/.*$', 'products/${1}'))->toBeTrue();
});

it('counts the groups of a pattern that does not match the empty string', function () {
    expect(validateGroupReferences('^vestigingen/([^/]+)/faqs/.+$', 'vestigingen/$1/#veelgestelde-vragen'))->toBeFalse();
});

it('counts a named group by its number', function () {
    expect(validateGroupReferences('^shop/(?<id>\d+)$', 'products/$1'))->toBeFalse();
});

it('does not count a non-capturing group', function () {
    expect(validateGroupReferences('^shop/(?:\d+)$', 'products/$1'))->toBeTrue();
});

it('leaves a pattern that does not compile to the regex rule', function () {
    expect(validateGroupReferences('(unclosed', 'products/$1'))->toBeFalse();
});

it('passes when value is empty', function () {
    expect(validateGroupReferences('^shop/(\d+)$', ''))->toBeFalse();
});

it('passes when value is null', function () {
    expect(validateGroupReferences('^shop/(\d+)$', null))->toBeFalse();
});
