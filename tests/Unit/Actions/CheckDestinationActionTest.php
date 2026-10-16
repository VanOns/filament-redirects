<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use VanOns\FilamentRedirects\Actions\CheckDestinationAction;
use VanOns\FilamentRedirects\Enums\DestinationHealth;
use VanOns\FilamentRedirects\Enums\Keys;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Events\DestinationBroken;
use VanOns\FilamentRedirects\Models\Redirect;

beforeEach(function () {
    config()->set('app.url', 'https://example.com');
    Http::preventStrayRequests();
});

function redirectWithDestination(?string $to, Type $type = Type::Static, string $from = 'old'): Redirect
{
    return Redirect::create(['from' => $from, 'to' => $to, 'type' => $type, 'status_code' => 301, 'active' => true]);
}

it('stores a destination that answers 200 as healthy', function () {
    Http::fake(['https://partner.test/*' => Http::response(status: 200)]);
    $redirect = redirectWithDestination('https://partner.test/offer');

    $health = (new CheckDestinationAction($redirect))();

    $redirect->refresh();
    expect($health)->toBe(DestinationHealth::Healthy)
        ->and($redirect->destination_health)->toBe(DestinationHealth::Healthy)
        ->and($redirect->destination_status)->toBe(200)
        ->and($redirect->destination_error)->toBeNull()
        ->and($redirect->destination_checked_at)->not->toBeNull();
});

it('stores a destination that answers 404 as broken', function () {
    Http::fake(['https://partner.test/*' => Http::response(status: 404)]);
    $redirect = redirectWithDestination('https://partner.test/gone');

    (new CheckDestinationAction($redirect))();

    expect($redirect->refresh()->destination_health)->toBe(DestinationHealth::Broken)
        ->and($redirect->destination_status)->toBe(404);
});

it('stores a destination that answers 500 as broken', function () {
    Http::fake(['https://partner.test/*' => Http::response(status: 500)]);
    $redirect = redirectWithDestination('https://partner.test/error');

    (new CheckDestinationAction($redirect))();

    expect($redirect->refresh()->destination_health)->toBe(DestinationHealth::Broken);
});

it('stores a destination that refuses the check as blocked', function (int $status) {
    Http::fake(['https://partner.test/*' => Http::response(status: $status)]);
    $redirect = redirectWithDestination('https://partner.test/members');

    (new CheckDestinationAction($redirect))();

    expect($redirect->refresh()->destination_health)->toBe(DestinationHealth::Blocked)
        ->and($redirect->destination_status)->toBe($status);
})->with([401, 403, 429]);

it('stores a destination that does not answer as broken with the error', function () {
    Http::fake(['https://partner.test/*' => Http::failedConnection('Could not resolve host: partner.test')]);
    $redirect = redirectWithDestination('https://partner.test/offer');

    $health = (new CheckDestinationAction($redirect))();

    $redirect->refresh();
    expect($health)->toBe(DestinationHealth::Broken)
        ->and($redirect->destination_status)->toBeNull()
        ->and($redirect->destination_error)->toContain('Could not resolve host');
});

it('stores a destination that is no valid url as broken', function () {
    $redirect = redirectWithDestination('https://partner .test/offer');

    (new CheckDestinationAction($redirect))();

    expect($redirect->refresh()->destination_health)->toBe(DestinationHealth::Broken)
        ->and($redirect->destination_error)->toContain('partner .test');
});

it('follows the redirects of a destination to where it ends up', function () {
    Http::fake([
        'https://partner.test/old' => Http::response(status: 301, headers: ['Location' => 'https://partner.test/new']),
        'https://partner.test/new' => Http::response(status: 404),
    ]);
    $redirect = redirectWithDestination('https://partner.test/old');

    (new CheckDestinationAction($redirect))();

    expect($redirect->refresh()->destination_health)->toBe(DestinationHealth::Broken)
        ->and($redirect->destination_status)->toBe(404);
});

it('stores a destination that keeps redirecting as broken', function () {
    Http::fake(['https://partner.test/*' => Http::response(status: 302, headers: ['Location' => 'https://partner.test/loop'])]);
    $redirect = redirectWithDestination('https://partner.test/loop');

    (new CheckDestinationAction($redirect))();

    expect($redirect->refresh()->destination_health)->toBe(DestinationHealth::Broken);
});

it('requests a path on this site on the app url', function () {
    Http::fake(['https://example.com/*' => Http::response(status: 200)]);

    (new CheckDestinationAction(redirectWithDestination('blog/new')))();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/blog/new');
});

it('requests the home page for a redirect without a destination', function () {
    Http::fake(['https://example.com/*' => Http::response(status: 200)]);

    (new CheckDestinationAction(redirectWithDestination(null)))();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://example.com/');
});

it('requests a path on this site on the configured base url', function () {
    config()->set('filament-redirects.checks.base_url', 'http://localhost:8080/');
    Http::fake(['http://localhost:8080/*' => Http::response(status: 200)]);

    (new CheckDestinationAction(redirectWithDestination('blog/new')))();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://localhost:8080/blog/new');
});

it('requests a protocol-relative destination over https', function () {
    Http::fake(['https://cdn.partner.test/*' => Http::response(status: 200)]);

    (new CheckDestinationAction(redirectWithDestination('//cdn.partner.test/file.pdf')))();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.partner.test/file.pdf');
});

it('identifies itself with the configured user agent', function () {
    config()->set('filament-redirects.checks.user_agent', 'RedirectChecker/1.0');
    Http::fake(['https://partner.test/*' => Http::response(status: 200)]);

    (new CheckDestinationAction(redirectWithDestination('https://partner.test/offer')))();

    Http::assertSent(fn (Request $request) => $request->header('User-Agent') === ['RedirectChecker/1.0']);
});

it('checks a match redirect that sends every visitor to the same destination', function () {
    Http::fake(['https://example.com/*' => Http::response(status: 200)]);
    $redirect = redirectWithDestination('blog', Type::Match, '^blog/[0-9]+/.*$');

    expect((new CheckDestinationAction($redirect))())->toBe(DestinationHealth::Healthy);
});

it('does not check a destination that depends on the visited url', function (Redirect $redirect) {
    Http::fake();

    $health = (new CheckDestinationAction($redirect))();

    expect($health)->toBeNull()
        ->and($redirect->refresh()->destination_checked_at)->toBeNull();
    Http::assertNothingSent();
})->with([
    'match with a group' => fn () => redirectWithDestination('archive/$1', Type::Match, '^blog/([0-9]+)$'),
    'replace' => fn () => redirectWithDestination('nl/blog', Type::Replace, 'en/blog'),
]);

it('does not check a destination that is no page', function (string $to) {
    Http::fake();

    expect((new CheckDestinationAction(redirectWithDestination($to)))())->toBeNull();
    Http::assertNothingSent();
})->with(['mailto:info@example.com', 'tel:+31201234567', 'ftp://files.example.com/x']);

it('keeps the updated at of the redirect', function () {
    Http::fake(['https://partner.test/*' => Http::response(status: 200)]);
    $redirect = redirectWithDestination('https://partner.test/offer');
    Redirect::query()->whereKey($redirect->id)->toBase()->update(['updated_at' => '2026-01-01 12:00:00']);

    (new CheckDestinationAction($redirect->refresh()))();

    expect($redirect->refresh()->updated_at->toDateTimeString())->toBe('2026-01-01 12:00:00');
});

it('keeps the redirect cache', function () {
    Http::fake(['https://partner.test/*' => Http::response(status: 404)]);
    $redirect = redirectWithDestination('https://partner.test/offer');
    Cache::put(Keys::Cache->value, 'cached');

    (new CheckDestinationAction($redirect))();

    expect(Cache::get(Keys::Cache->value))->toBe('cached');
});

it('dispatches an event when the destination breaks', function () {
    Event::fake([DestinationBroken::class]);
    Http::fake(['https://partner.test/*' => Http::response(status: 404)]);
    $redirect = redirectWithDestination('https://partner.test/gone');

    (new CheckDestinationAction($redirect))();

    Event::assertDispatched(DestinationBroken::class, fn (DestinationBroken $event) => $event->redirect->is($redirect));
});

it('does not dispatch the event again while the destination stays broken', function () {
    Event::fake([DestinationBroken::class]);
    Http::fake(['https://partner.test/*' => Http::response(status: 404)]);
    $redirect = redirectWithDestination('https://partner.test/gone');

    (new CheckDestinationAction($redirect))();
    (new CheckDestinationAction($redirect))();

    Event::assertDispatchedTimes(DestinationBroken::class, 1);
});

it('does not dispatch the event for a destination that works', function () {
    Event::fake([DestinationBroken::class]);
    Http::fake(['https://partner.test/*' => Http::response(status: 200)]);

    (new CheckDestinationAction(redirectWithDestination('https://partner.test/offer')))();

    Event::assertNotDispatched(DestinationBroken::class);
});
