<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use VanOns\FilamentRedirects\Actions\RedirectAction;
use VanOns\FilamentRedirects\Enums\Keys;
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

it('redirects to the captured groups of a match type', function () {
    Redirect::create(['from' => '^vestigingen/([^/]+)/faqs/.*$', 'to' => 'vestigingen/$1/#veelgestelde-vragen', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $result = (new RedirectAction('vestigingen/breda/faqs/wat-kost-advies'))();
    expect($result->getTargetUrl())->toBe(url('vestigingen/breda/#veelgestelde-vragen'));
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

it('does not count a hit from the destination check', function () {
    $redirect = Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);
    $request = Request::create('/old-page', server: ['HTTP_USER_AGENT' => config('filament-redirects.checks.user_agent')]);

    $result = (new RedirectAction('old-page', request: $request))();

    expect($result->getTargetUrl())->toContain('new-page')
        ->and($redirect->fresh()->hits)->toBe(0);
});

it('counts a hit from a visitor without a user agent', function () {
    config()->set('filament-redirects.checks.user_agent', null);
    $redirect = Redirect::create(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $request = Request::create('/old-page');
    $request->headers->remove('User-Agent');

    (new RedirectAction('old-page', request: $request))();

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

it('lets priority decide between a static redirect and a pattern', function () {
    Redirect::create(['from' => '^blog/.*', 'to' => 'pattern', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'priority' => 1, 'include_headers' => false, 'include_query' => false]);
    $static = Redirect::create(['from' => 'blog/post', 'to' => 'static', 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'priority' => 2, 'include_headers' => false, 'include_query' => false]);

    expect((new RedirectAction('blog/post'))()->getTargetUrl())->toContain('pattern');

    $static->update(['priority' => 0]);

    expect((new RedirectAction('blog/post'))()->getTargetUrl())->toContain('static');
});

it('only loads the static redirects of the requested path', function () {
    foreach (range(1, 20) as $i) {
        Redirect::create(['from' => "page-{$i}", 'to' => "new-{$i}", 'type' => Type::Static, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);
    }
    Redirect::create(['from' => '^blog/.*', 'to' => 'blog', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    $loaded = 0;
    Event::listen('eloquent.retrieved: ' . Redirect::class, function () use (&$loaded) {
        $loaded++;
    });

    expect((new RedirectAction('page-7'))()->getTargetUrl())->toContain('new-7')
        ->and($loaded)->toBe(2);
});

it('caches only the pattern redirects', function () {
    config()->set('filament-redirects.cache.enabled', true);
    Redirect::create(['from' => '^blog/.*', 'to' => 'blog', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    (new RedirectAction('anything'))();

    // Written without model events, so the cache is not cleared: the static lookup does not need it.
    Redirect::query()->insert(['from' => 'old-page', 'to' => 'new-page', 'type' => Type::Static->value, 'status_code' => 301, 'active' => true, 'priority' => 2, 'include_headers' => false, 'include_query' => false]);

    expect(Cache::get(Keys::Cache->value))->toHaveCount(1)
        ->and((new RedirectAction('old-page'))()->getTargetUrl())->toContain('new-page');
});

it('does not let a captured group send the visitor off-site', function () {
    Redirect::create(['from' => '^nl/(.*)$', 'to' => '$1', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    expect((new RedirectAction('nl/https://evil.test'))())->toBeNull()
        ->and((new RedirectAction('nl///evil.test'))())->toBeNull()
        ->and((new RedirectAction('nl/over-ons'))()->getTargetUrl())->toBe(url('over-ons'));
});

it('lets a captured group go only to the host the destination names', function () {
    Redirect::create(['from' => '^nl/(.*)$', 'to' => 'https://other.test$1', 'type' => Type::Match, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    expect((new RedirectAction('nl//blog'))()->getTargetUrl())->toBe('https://other.test/blog')
        ->and((new RedirectAction('nl/.evil.test'))())->toBeNull();
});

it('does not let a replace redirect send the visitor off-site', function () {
    Redirect::create(['from' => 'nl/', 'to' => '', 'type' => Type::Replace, 'status_code' => 301, 'active' => true, 'include_headers' => false, 'include_query' => false]);

    expect((new RedirectAction('nl///evil.test'))())->toBeNull()
        ->and((new RedirectAction('nl/blog'))()->getTargetUrl())->toBe(url('blog'));
});
