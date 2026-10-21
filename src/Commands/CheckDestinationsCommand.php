<?php

namespace VanOns\FilamentRedirects\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use VanOns\FilamentRedirects\Actions\CheckDestinationAction;
use VanOns\FilamentRedirects\Enums\Type;
use VanOns\FilamentRedirects\Jobs\CheckDestination;
use VanOns\FilamentRedirects\Models\Redirect;

/**
 * Checks whether the destinations of the active redirects still work.
 */
class CheckDestinationsCommand extends Command
{
    protected $signature = 'filament-redirects:check
        {--id=* : Only check the redirects with these ids}
        {--sync : Check right away instead of on the queue}';

    protected $description = 'Check whether the destinations of the active redirects still work';

    public function handle(): int
    {
        $redirects = $this->checkableRedirects();

        if ($this->option('sync')) {
            $results = $redirects
                ->map(fn (Redirect $redirect) => (new CheckDestinationAction($redirect))()?->getLabel())
                ->countBy()
                ->all();

            $this->components->info('Checked '.array_sum($results).' destinations.');

            foreach ($results as $health => $count) {
                $this->components->twoColumnDetail((string) $health, (string) $count);
            }

            return self::SUCCESS;
        }

        $queued = 0;

        foreach ($redirects as $redirect) {
            CheckDestination::dispatch($redirect);
            $queued++;
        }

        $this->components->info("Queued {$queued} destination checks.");

        return self::SUCCESS;
    }

    /**
     * @return LazyCollection<int, Redirect>
     */
    private function checkableRedirects(): LazyCollection
    {
        /** @var array<int, string> $ids */
        $ids = $this->option('id');

        return Redirect::query()
            ->active()
            ->where('type', '!=', Type::Replace)
            ->when($ids !== [], fn (Builder $query) => $query->whereKey($ids))
            ->lazyById()
            ->filter(fn (Redirect $redirect) => $redirect->checkableDestination() !== null);
    }
}
