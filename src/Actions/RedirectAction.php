<?php

namespace VanOns\FilamentRedirects\Actions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use VanOns\FilamentRedirects\Enums\Keys;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Models\Redirect;

class RedirectAction
{
    public string $path;
    public array $headers = [];
    public Request $request;

    public function __construct(
        ?string $path = null,
        ?array $headers = null,
        ?Request $request = null,
    ) {
        $this->path = $path ?? request()->path();
        $this->headers = $headers ?? request()->headers->all();
        $this->request = $request ?? request();
    }

    /**
     * @var Collection<int,Redirect>
     */
    private Collection $redirects;

    /**
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws HttpResponseException
     */
    public function __invoke(): null|RedirectResponse|Redirector
    {
        $this->redirects = $this->getRedirects();

        $redirect = $this->findRedirect();

        if ($redirect) {
            return $this->redirectTo($redirect);
        }

        return null;
    }

    private function redirectTo(Redirect $route): RedirectResponse|Redirector
    {
        $route->hit();

        return redirect(
            to: $route->createUrl(),
            status: $route->status_code,
            headers: $route->include_headers ? $this->headers : []
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
                Type::Static => $redirect->from === $this->path,
                Type::Match => preg_match("/{$redirect->from}/", $this->path),
                Type::Replace => str_contains($redirect->from, $this->path),
            };
        });
    }
}
