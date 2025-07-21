<?php

namespace VanOns\FilamentRedirects\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use VanOns\FilamentRedirects\Actions\RedirectAction;

class RedirectController extends Controller
{
    public function __invoke(): null|RedirectResponse
    {
        if ($redirect = (new RedirectAction())()) {
            return $redirect;
        }

        abort(404);
    }
}
