<?php

namespace VanOns\FilamentRedirects\Actions;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use VanOns\FilamentRedirects\Enums\Keys;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Models\Redirect;

class RedirectAction extends Controller
{
    /**
     * @var Collection<int,Redirect>
     */
    private Collection $redirects;

    /**
     * @throws BindingResolutionException
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws HttpResponseException
     */
    public function __invoke(): RedirectResponse|Redirector
    {
        $this->redirects = $this->getRedirects();

        $redirect = $this->findRedirect();

        if ($redirect) {
            return $this->redirectTo($redirect);
        }

        abort(404);
    }

    private function redirectTo(Redirect $route): RedirectResponse
    {
        $route->hit();

        return redirect(
            to: $route->createUrl(),
            status: $route->status_code,
            headers: $route->include_headers ? request()->headers->all() : []
        );
    }

    /**
     * @return Collection<int,Redirect>
     */
    private function getRedirects(): Collection
    {
        $query = function () {
            return Redirect::query()
                ->active()
                ->orderBy('priority')
                ->get();
        };

        if (config('filament-redirects.cache.enabled', false)) {
            return Cache::remember(
                key: Keys::Cache->value,
                ttl: config('filament-redirects.cache.ttl', 60),
                callback: $query
            );
        }

        return $query();
    }

    private function findRedirect(): ?Redirect
    {
        return $this->redirects->first(function (Redirect $redirect) {
            return match ($redirect->type) {
                Type::Static => $redirect->from === request()->path(),
                Type::Match => preg_match("/{$redirect->from}/", request()->path()),
                Type::Replace => str_contains($redirect->from, request()->path()),
            };
        });
    }
}
