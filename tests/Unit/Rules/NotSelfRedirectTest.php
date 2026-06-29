<?php

use VanOns\FilamentRedirects\Rules\NotSelfRedirect;

it('fails when from equals to', function () {
    $rule = new NotSelfRedirect('old-page');
    $failed = false;
    $rule->validate('to', 'old-page', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('fails when from equals to ignoring trailing slash', function () {
    $rule = new NotSelfRedirect('old-page/');
    $failed = false;
    $rule->validate('to', 'old-page', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('passes when from differs from to', function () {
    $rule = new NotSelfRedirect('old-page');
    $failed = false;
    $rule->validate('to', 'new-page', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('passes when value is empty', function () {
    $rule = new NotSelfRedirect('old-page');
    $failed = false;
    $rule->validate('to', '', function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

it('passes when value is null', function () {
    $rule = new NotSelfRedirect('old-page');
    $failed = false;
    $rule->validate('to', null, function () use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});
