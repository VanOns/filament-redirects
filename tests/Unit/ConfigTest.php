<?php

it('has sensible defaults', function () {
    expect(config('filament-redirects.add_middleware'))->toBeTrue()
        ->and(config('filament-redirects.add_route'))->toBeFalse()
        ->and(config('filament-redirects.forwarded_headers'))->toBe([])
        ->and(config('filament-redirects.cache.enabled'))->toBeFalse()
        ->and(config('filament-redirects.default_status_code'))->toBe(301);
});
