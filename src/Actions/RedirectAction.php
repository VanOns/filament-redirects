<?php

namespace VanOns\FilamentRedirects\Actions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
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
     * @throws HttpException
     * @throws NotFoundHttpException
     * @throws HttpResponseException
     */
    public function __invoke(): null|RedirectResponse|Redirector
    {
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
            to: $route->createUrl($this->path),
            status: $route->status_code,
            headers: $this->forwardedHeaders($route)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forwardedHeaders(Redirect $route): array
    {
        if (!$route->include_headers) {
            return [];
        }

        /** @var array<int, string> $allowed */
        $allowed = config('filament-redirects.forwarded_headers', []);

        $headers = Arr::only(
            array_change_key_case($this->headers),
            array_map(strtolower(...), $allowed)
        );

        if (empty($headers)) {
            return [];
        }

        // forwarded values belong to one visitor's request, so keep the response out of caches
        return $headers + ['Cache-Control' => 'no-store'];
    }

    private function findRedirect(): ?Redirect
    {
        return $this->staticRedirects()
            ->concat($this->patternRedirects())
            ->sortBy('priority')
            ->first(fn (Redirect $redirect) => $redirect->matches($this->path));
    }

    /**
     * A static redirect only matches its own path, so it is looked up by that path instead of
     * loading every redirect on every request.
     *
     * @return Collection<int,Redirect>
     */
    private function staticRedirects(): Collection
    {
        return Redirect::query()
            ->active()
            ->where('type', Type::Static)
            ->where('from', $this->path)
            ->get();
    }

    /**
     * Match and replace redirects have to be tried one by one; these are the ones the cache holds.
     *
     * @return Collection<int,Redirect>
     */
    private function patternRedirects(): Collection
    {
        $query = function () {
            return Redirect::query()
                ->active()
                ->where('type', '!=', Type::Static)
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
}
