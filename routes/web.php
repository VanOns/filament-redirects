<?php

use Illuminate\Support\Facades\Route;

if (config('filament-redirects.add_route')) {
    Route::fallback(VanOns\Redirector\Actions\RedirectAction::class);
}
