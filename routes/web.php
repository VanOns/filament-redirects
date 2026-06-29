<?php

use Illuminate\Support\Facades\Route;
use VanOns\FilamentRedirects\Actions\RedirectAction;

if (config('filament-redirects.add_route')) {
    Route::fallback(RedirectAction::class);
}
