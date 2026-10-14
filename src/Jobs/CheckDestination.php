<?php

namespace VanOns\FilamentRedirects\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentRedirects\Actions\CheckDestinationAction;
use VanOns\FilamentRedirects\Models\Redirect;

/**
 * Checks the destination of one redirect on the queue.
 */
class CheckDestination implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Redirect $redirect,
    ) {
        $this->onQueue(config('filament-redirects.checks.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->redirect->getKey();
    }

    public function handle(): void
    {
        (new CheckDestinationAction($this->redirect))();
    }
}
