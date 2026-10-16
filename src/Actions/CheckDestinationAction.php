<?php

namespace VanOns\FilamentRedirects\Actions;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Client\ClientExceptionInterface;
use VanOns\FilamentRedirects\Enums\DestinationHealth;
use VanOns\FilamentRedirects\Events\DestinationBroken;
use VanOns\FilamentRedirects\Models\Redirect;

/**
 * Requests the destination of a redirect and stores whether it still works.
 */
class CheckDestinationAction
{
    public function __construct(
        public Redirect $redirect,
    ) {
    }

    /**
     * The health found, or null when the destination is not checked because it depends on the visited URL.
     */
    public function __invoke(): ?DestinationHealth
    {
        $url = $this->redirect->checkableDestination();

        if ($url === null) {
            return null;
        }

        $wasBroken = $this->redirect->destination_health === DestinationHealth::Broken;

        try {
            $status = $this->statusOf($url);
            $error = null;
        } catch (ConnectionException|ClientExceptionInterface|MalformedUriException $exception) {
            $status = null;
            $error = Str::limit($exception->getMessage(), 250);
        }

        $health = $status === null ? DestinationHealth::Broken : DestinationHealth::fromStatus($status);

        // a check is no edit, so the redirect keeps its "updated at"
        Redirect::withoutTimestamps(fn () => $this->redirect->update([
            'destination_health' => $health,
            'destination_status' => $status,
            'destination_error' => $error,
            'destination_checked_at' => now(),
        ]));

        if ($health === DestinationHealth::Broken && !$wasBroken) {
            DestinationBroken::dispatch($this->redirect);
        }

        return $health;
    }

    /**
     * @throws ConnectionException
     * @throws MalformedUriException
     */
    private function statusOf(string $url): int
    {
        return Http::withUserAgent((string) config('filament-redirects.checks.user_agent'))
            ->timeout((int) config('filament-redirects.checks.timeout', 10))
            // only the status is read, so a large file behind the destination is never downloaded
            ->withOptions(['stream' => true])
            ->get($url)
            ->status();
    }
}
