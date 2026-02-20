<?php

namespace VanOns\FilamentRedirects\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use VanOns\FilamentRedirects\Actions\RedirectAction;

class RedirectMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return (new RedirectAction())() ?? $next($request);
    }
}
