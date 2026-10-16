<?php

use VanOns\FilamentRedirects\Enums\DestinationHealth;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Filament\Resources\RedirectResource;
use VanOns\FilamentRedirects\Models\Redirect;

beforeEach(function () {
    config()->set('app.url', 'https://example.com');
});

it('shows a static path with a leading slash and a pattern as written', function () {
    expect(RedirectResource::displayFrom(new Redirect(['from' => 'blog/old', 'type' => Type::Static])))->toBe('/blog/old')
        ->and(RedirectResource::displayFrom(new Redirect(['from' => '^blog/(\d+)$', 'type' => Type::Match])))->toBe('^blog/(\d+)$')
        ->and(RedirectResource::displayFrom(new Redirect(['from' => 'en/blog', 'type' => Type::Replace])))->toBe('en/blog');
});

it('shows a destination on this site as its path', function () {
    expect(RedirectResource::displayUrl('https://example.com/blog/new'))->toBe('/blog/new')
        ->and(RedirectResource::displayUrl('blog/new'))->toBe('/blog/new')
        ->and(RedirectResource::displayUrl('https://example.com'))->toBe('/')
        ->and(RedirectResource::displayUrl(''))->toBe('/');
});

it('marks a destination on another domain', function () {
    expect(RedirectResource::displayUrl('https://www.hypotheekbond.nl/adviseurs'))->toBe('↗ www.hypotheekbond.nl/adviseurs')
        ->and(RedirectResource::displayUrl('https://example.com.evil.test/x'))->toBe('↗ example.com.evil.test/x')
        ->and(RedirectResource::displayUrl('//cdn.example.net/x'))->toBe('↗ cdn.example.net/x');
});

it('shows the part a replace redirect puts in as written', function () {
    expect(RedirectResource::displayTo(new Redirect(['from' => 'en/blog', 'to' => 'nl/blog', 'type' => Type::Replace])))->toBe('nl/blog')
        ->and(RedirectResource::displayTo(new Redirect(['from' => 'old', 'to' => null, 'type' => Type::Static])))->toBe('/');
});

it('sums up what the last destination check found', function () {
    $this->freezeTime();
    $broken = new Redirect(['destination_health' => DestinationHealth::Broken, 'destination_status' => 404, 'destination_checked_at' => now()->subHours(2)]);
    $unreachable = new Redirect(['destination_health' => DestinationHealth::Broken, 'destination_error' => 'Could not resolve host', 'destination_checked_at' => now()->subHours(2)]);

    expect(RedirectResource::destinationCheckSummary($broken))->toBe('Broken: 404, checked 2 hours ago')
        ->and(RedirectResource::destinationCheckSummary($unreachable))->toBe('Broken: no response, checked 2 hours ago')
        ->and(RedirectResource::destinationCheckSummary(new Redirect()))->toBeNull();
});

it('counts the active redirects with a broken destination in the navigation badge', function () {
    config()->set('filament-redirects.checks.enabled', true);
    $attributes = ['to' => 'new', 'type' => Type::Static, 'status_code' => 301];
    Redirect::create([...$attributes, 'from' => 'a', 'active' => true, 'destination_health' => DestinationHealth::Broken]);
    Redirect::create([...$attributes, 'from' => 'b', 'active' => true, 'destination_health' => DestinationHealth::Healthy]);
    Redirect::create([...$attributes, 'from' => 'c', 'active' => false, 'destination_health' => DestinationHealth::Broken]);

    expect(RedirectResource::getNavigationBadge())->toBe('1');
});

it('shows no navigation badge without broken destinations or with the checks disabled', function () {
    config()->set('filament-redirects.checks.enabled', true);
    expect(RedirectResource::getNavigationBadge())->toBeNull();

    config()->set('filament-redirects.checks.enabled', false);
    Redirect::create(['from' => 'a', 'to' => 'new', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'destination_health' => DestinationHealth::Broken]);
    expect(RedirectResource::getNavigationBadge())->toBeNull();
});
