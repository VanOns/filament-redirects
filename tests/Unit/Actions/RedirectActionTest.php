<?php

use VanOns\FilamentRedirects\Actions\RedirectAction;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Models\Redirect;

it('returns null when no redirects exist', function () {
    $result = (new RedirectAction('some/path'))();
    expect($result)->toBeNull();
});

it('returns null when no redirect matches', function () {
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('other-page'))();
    expect($result)->toBeNull();
});

it('does not redirect an inactive redirect', function () {
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => false, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('old-page'))();
    expect($result)->toBeNull();
});

it('redirects on a static path match', function () {
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('old-page'))();
    expect($result)->not->toBeNull()
        ->and($result->getTargetUrl())->toContain('new-page')
        ->and($result->getStatusCode())->toBe(301);
});

it('uses the configured status code when redirecting', function () {
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 302, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('old-page'))();
    expect($result->getStatusCode())->toBe(302);
});

it('redirects using regex match type', function () {
    Redirect::create(['from' => 'blog/[0-9]+/.*', 'to' => 'blog', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('blog/123/my-old-post'))();
    expect($result)->not->toBeNull()
        ->and($result->getTargetUrl())->toContain('blog')
        ->and($result->getStatusCode())->toBe(301);
});

it('does not match when regex does not match for match type', function () {
    Redirect::create(['from' => 'blog/[0-9]+/.*', 'to' => 'blog', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('news/article'))();
    expect($result)->toBeNull();
});

it('redirects using replace type', function () {
    Redirect::create(['from' => 'en/blog', 'to' => 'nl/blog', 'type' => Type::Replace, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('en/blog/my-post'))();
    expect($result)->not->toBeNull()
        ->and($result->getTargetUrl())->toContain('nl/blog/my-post')
        ->and($result->getStatusCode())->toBe(301);
});

it('does not match when substring is absent for replace type', function () {
    Redirect::create(['from' => 'en/blog', 'to' => 'nl/blog', 'type' => Type::Replace, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('fr/blog/my-post'))();
    expect($result)->toBeNull();
});

it('increments hit count on a successful redirect', function () {
    $redirect = Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    (new RedirectAction('old-page'))();

    expect($redirect->fresh()->hits)->toBe(1);
});

it('does not forward request headers when none are configured', function () {
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => true, 'include_query' => false]);

    $result = (new RedirectAction('old-page', ['x-custom' => ['value'], 'cookie' => ['session=abc']]))();

    expect($result->headers->has('x-custom'))->toBeFalse()
        ->and($result->headers->has('cookie'))->toBeFalse();
});

it('forwards only the configured request headers', function () {
    config()->set('filament-redirects.forwarded_headers', ['X-Custom']);
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => true, 'include_query' => false]);

    $result = (new RedirectAction('old-page', ['x-custom' => ['value'], 'cookie' => ['session=abc']]))();

    expect($result->headers->get('x-custom'))->toBe('value')
        ->and($result->headers->has('cookie'))->toBeFalse()
        ->and($result->headers->get('cache-control'))->toContain('no-store');
});

it('does not forward configured headers when include_headers is disabled', function () {
    config()->set('filament-redirects.forwarded_headers', ['X-Custom']);
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('old-page', ['x-custom' => ['value']]))();

    expect($result->headers->has('x-custom'))->toBeFalse();
});

it('skips a match redirect whose pattern does not compile', function () {
    Redirect::create(['from' => '(unclosed', 'to' => 'blocked', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);
    Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    expect((new RedirectAction('unrelated-page'))())->toBeNull()
        ->and((new RedirectAction('old-page'))()->getTargetUrl())->toContain('new-page');
});

it('matches a pattern containing slashes without extra escaping', function () {
    Redirect::create(['from' => '^en/blog/[0-9]+$', 'to' => 'blog', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    expect((new RedirectAction('en/blog/12'))())->not->toBeNull()
        ->and((new RedirectAction('nl/en/blog/12'))())->toBeNull();
});

it('evaluates redirects in priority order', function () {
    Redirect::create(['from' => 'page', 'to' => 'first', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'priority' => 1, 'include_headers' => false, 'include_query' => false]);
    Redirect::create(['from' => 'page', 'to' => 'second', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'priority' => 2, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('page'))();
    expect($result->getTargetUrl())->toContain('first');
});
