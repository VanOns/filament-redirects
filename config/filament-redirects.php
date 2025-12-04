<?php

return [
    'add_route' => env('FILAMENT_REDIRECTS_ADD_ROUTE', true),
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
    'cache' => [
        'enabled' => false,
        'ttl' => 60,
    ],
];
