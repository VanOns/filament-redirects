<?php

use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Models\Redirect;
use VanOns\FilamentRedirects\Rules\NoCircularRedirect;

it('fails on a direct circular redirect', function () {
    Redirect::create(['from' => 'b', 'to' => 'a', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    $rule = new NoCircularRedirect('a');
    $failed = false;
    $rule->validate('to', 'b', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('fails on an indirect circular redirect chain', function () {
    Redirect::create(['from' => 'b', 'to' => 'c', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);
    Redirect::create(['from' => 'c', 'to' => 'a', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    $rule = new NoCircularRedirect('a');
    $failed = false;
    $rule->validate('to', 'b', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('fails on a circular chain through a match redirect', function () {
    Redirect::create(['from' => '^b$', 'to' => 'a', 'type' => Type::Match, 'status_code' => 301, 'active' => true]);

    $rule = new NoCircularRedirect('a');
    $failed = false;
    $rule->validate('to', 'b', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('fails on a circular chain through a replace redirect', function () {
    Redirect::create(['from' => 'b', 'to' => 'a', 'type' => Type::Replace, 'status_code' => 301, 'active' => true]);

    $rule = new NoCircularRedirect('a');
    $failed = false;
    $rule->validate('to', 'b', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('passes when no circular chain exists', function () {
    Redirect::create(['from' => 'b', 'to' => 'c', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    $rule = new NoCircularRedirect('a');
    $failed = false;
    $rule->validate('to', 'b', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('passes when from is empty', function () {
    $rule = new NoCircularRedirect('');
    $failed = false;
    $rule->validate('to', 'b', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('passes when value is empty', function () {
    $rule = new NoCircularRedirect('a');
    $failed = false;
    $rule->validate('to', '', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('ignores inactive redirects when checking circular chains', function () {
    Redirect::create(['from' => 'b', 'to' => 'a', 'type' => Type::Static, 'status_code' => 301, 'active' => false]);

    $rule = new NoCircularRedirect('a');
    $failed = false;
    $rule->validate('to', 'b', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});
