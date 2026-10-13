<?php

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
