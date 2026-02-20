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
    'cache' => [
        'enabled' => false,
        'ttl' => 60,
    ],
];
