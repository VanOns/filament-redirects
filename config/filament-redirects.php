<?php

return [
    'add_middleware' => env('FILAMENT_REDIRECTS_ADD_MIDDLEWARE', true),
    'add_route' => env('FILAMENT_REDIRECTS_ADD_ROUTE', false),
    /**
     * Make sure you have translations matching the status codes in your
     * language files.
     */
    'status_codes' => [
        301,
        302,
        303,
        307,
        308,
    ],
    /**
     * The default status code inside the Filament Form. To change the database default, update the migration file or create a new migration.
     */
    'default_status_code' => 301,
    /**
     * Request headers that may be copied onto the redirect response when a redirect has
     * "include headers" enabled. Anything not listed here is left off the response.
     */
    'forwarded_headers' => [],
    'cache' => [
        'enabled' => false,
        'ttl' => 60,
    ],
    // Checks whether the destination of each redirect still works. This requests every destination, so it is off by default.
    'checks' => [
        'enabled' => env('FILAMENT_REDIRECTS_CHECKS_ENABLED', false),
        // A cron expression, or null to schedule the `filament-redirects:check` command yourself.
        'schedule' => '0 3 * * *',
        'queue' => null,
        'timeout' => 10,
        // Relative destinations are requested on this URL instead of app.url, for a server that cannot reach its own public URL.
        'base_url' => null,
        'user_agent' => 'Mozilla/5.0 (compatible; FilamentRedirects; +https://github.com/VanOns/filament-redirects)',
    ],

    /**
     * If the application URL is not where the redirects will be active, set the base URL for the redirects here.
     */
    'base_url' => env('FILAMENT_REDIRECTS_BASE_URL'),
];
