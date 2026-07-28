<?php

use Illuminate\Support\Facades\Cache;
use VanOns\FilamentRedirects\Enums\Keys;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Models\Redirect;

it('increments hits and sets last_hit on hit()', function () {
    $redirect = Redirect::create(['from' => 'old', 'to' => 'new', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);
    expect($redirect->fresh()->hits)->toBe(0)
        ->and($redirect->fresh()->last_hit)->toBeNull();

    $redirect->hit();

    expect($redirect->fresh()->hits)->toBe(1)
        ->and($redirect->fresh()->last_hit)->not->toBeNull();
});

it('clears cache when redirect is created', function () {
    Cache::put(Keys::Cache->value, 'test-value');

    Redirect::create(['from' => 'old', 'to' => 'new', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    expect(Cache::has(Keys::Cache->value))->toBeFalse();
});

it('clears cache when redirect is updated', function () {
    $redirect = Redirect::create(['from' => 'old', 'to' => 'new', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    Cache::put(Keys::Cache->value, 'test-value');
    $redirect->update(['to' => 'other']);

    expect(Cache::has(Keys::Cache->value))->toBeFalse();
});

it('keeps cache when only the hit counter changes', function () {
    $redirect = Redirect::create(['from' => 'old', 'to' => 'new', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    Cache::put(Keys::Cache->value, 'test-value');
    $redirect->hit();

    expect(Cache::has(Keys::Cache->value))->toBeTrue();
});

it('clears cache when redirect is soft deleted', function () {
    $redirect = Redirect::create(['from' => 'old', 'to' => 'new', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    Cache::put(Keys::Cache->value, 'test-value');
    $redirect->delete();

    expect(Cache::has(Keys::Cache->value))->toBeFalse();
});

it('clears cache when redirect is restored', function () {
    $redirect = Redirect::create(['from' => 'old', 'to' => 'new', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);
    $redirect->delete();

    Cache::put(Keys::Cache->value, 'test-value');
    $redirect->restore();

    expect(Cache::has(Keys::Cache->value))->toBeFalse();
});

it('only returns active redirects through scope', function () {
    Redirect::create(['from' => 'active', 'to' => 'a', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);
    Redirect::create(['from' => 'inactive', 'to' => 'b', 'type' => Type::Static, 'status_code' => 301, 'active' => false]);

    expect(Redirect::active()->count())->toBe(1)
        ->and(Redirect::active()->first()->from)->toBe('active');
});

it('assigns incrementing priority on create when none given', function () {
    $first = Redirect::create(['from' => 'a', 'to' => 'b', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);
    $second = Redirect::create(['from' => 'c', 'to' => 'd', 'type' => Type::Static, 'status_code' => 301, 'active' => true]);

    expect($second->priority)->toBeGreaterThan($first->priority);
});

it('builds correct url for static type', function () {
    $redirect = new Redirect(['from' => 'old', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'include_query' => false]);

    expect($redirect->createUrl('old'))->toBe('new-page');
});

it('builds correct url for replace type', function () {
    $redirect = new Redirect(['from' => 'en/blog', 'to' => 'nl/blog', 'type' => Type::Replace, 'status_code' => 301, 'include_query' => false]);

    expect($redirect->createUrl('en/blog/my-post'))->toBe('nl/blog/my-post');
});
