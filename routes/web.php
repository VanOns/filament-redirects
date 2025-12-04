<?php

use VanOns\FilamentRedirects\Actions\RedirectAction;
use Illuminate\Support\Facades\Route;

if (config('filament-redirects.add_route')) {
    Route::fallback(RedirectAction::class);
}
