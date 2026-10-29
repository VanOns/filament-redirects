<?php

namespace VanOns\FilamentRedirects\Helpers;

class RedirectHelper
{
    public static function getBaseUrl(bool $forChecks = false): string
    {
        if ($forChecks) {
            $baseUrl = (string)config('filament-redirects.checks.base_url');
        }

        if (empty($baseUrl)) {
            $baseUrl = (string)(config('filament-redirects.base_url') ?? config('app.url'));
        }

        return rtrim($baseUrl, '/');
    }
}
