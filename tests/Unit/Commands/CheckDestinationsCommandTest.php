<?php

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use VanOns\FilamentRedirects\Enums\DestinationHealth;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Jobs\CheckDestination;
use VanOns\FilamentRedirects\Models\Redirect;

beforeEach(function () {
    config()->set('app.url', 'https://example.com');
    Http::preventStrayRequests();
});

function redirectFor(string $to, Type $type = Type::Static, bool $active = true): Redirect
{
    return Redirect::create(['from' => 'old-'.uniqid(), 'to' => $to, 'type' => $type, 'status_code' => 301, 'active' => $active]);
}

it('queues a check for every active redirect with a checkable destination', function () {
    Queue::fake();
    $static = redirectFor('https://partner.test/offer');
    $match = redirectFor('blog', Type::Match);
    redirectFor('archive/$1', Type::Match);
    redirectFor('nl/blog', Type::Replace);
    redirectFor('https://partner.test/inactive', active: false);

    $this->artisan('filament-redirects:check')
        ->expectsOutputToContain('Queued 2 destination checks.')
        ->assertSuccessful();

    Queue::assertPushed(CheckDestination::class, 2);
    Queue::assertPushed(CheckDestination::class, fn (CheckDestination $job) => $job->redirect->is($static));
    Queue::assertPushed(CheckDestination::class, fn (CheckDestination $job) => $job->redirect->is($match));
});

it('queues the checks on the configured queue', function () {
    Queue::fake();
    config()->set('filament-redirects.checks.queue', 'checks');
    redirectFor('https://partner.test/offer');

    $this->artisan('filament-redirects:check')->assertSuccessful();

    Queue::assertPushedOn('checks', CheckDestination::class);
});

it('only checks the redirects with the given ids', function () {
    Queue::fake();
    $checked = redirectFor('https://partner.test/offer');
    redirectFor('https://partner.test/other');

    $this->artisan('filament-redirects:check', ['--id' => [$checked->id]])->assertSuccessful();

    Queue::assertPushed(CheckDestination::class, 1);
    Queue::assertPushed(CheckDestination::class, fn (CheckDestination $job) => $job->redirect->is($checked));
});

it('checks right away with the sync option', function () {
    Queue::fake();
    Http::fake([
        'https://partner.test/offer' => Http::response(status: 200),
        'https://partner.test/gone' => Http::response(status: 404),
    ]);
    $healthy = redirectFor('https://partner.test/offer');
    $broken = redirectFor('https://partner.test/gone');

    $this->artisan('filament-redirects:check', ['--sync' => true])
        ->expectsOutputToContain('Checked 2 destinations.')
        ->assertSuccessful();

    Queue::assertNothingPushed();
    expect($healthy->refresh()->destination_health)->toBe(DestinationHealth::Healthy)
        ->and($broken->refresh()->destination_health)->toBe(DestinationHealth::Broken);
});

it('runs the queued check', function () {
    Http::fake(['https://partner.test/*' => Http::response(status: 404)]);
    $redirect = redirectFor('https://partner.test/gone');

    CheckDestination::dispatchSync($redirect);

    expect($redirect->refresh()->destination_health)->toBe(DestinationHealth::Broken);
});

function scheduledChecks(): array
{
    return collect(app()->make(Schedule::class)->events())
        ->filter(fn (ScheduledEvent $event) => str_contains((string) $event->command, 'filament-redirects:check'))
        ->values()
        ->all();
}

it('schedules the checks when they are enabled', function () {
    config()->set('filament-redirects.checks.enabled', true);
    config()->set('filament-redirects.checks.schedule', '30 4 * * *');

    $checks = scheduledChecks();

    expect($checks)->toHaveCount(1)
        ->and($checks[0]->expression)->toBe('30 4 * * *');
});

it('does not schedule the checks when they are disabled', function () {
    config()->set('filament-redirects.checks.enabled', false);

    expect(scheduledChecks())->toBeEmpty();
});

it('does not schedule the checks without a schedule', function () {
    config()->set('filament-redirects.checks.enabled', true);
    config()->set('filament-redirects.checks.schedule', null);

    expect(scheduledChecks())->toBeEmpty();
});
