<?php

namespace VanOns\FilamentRedirects\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VanOns\FilamentRedirects\Models\Redirect;

/**
 * Dispatched when a check finds the destination of a redirect broken, once until it works again.
 */
class DestinationBroken
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Redirect $redirect,
    ) {
    }
}
