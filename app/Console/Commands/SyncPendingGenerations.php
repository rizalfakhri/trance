<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Exceptions\Providers\ProviderException;
use App\Generations\ApplyProviderUpdate;
use App\Models\Generation;
use App\Registries\ProviderRegistry;
use Illuminate\Console\Command;

class SyncPendingGenerations extends Command
{
    protected $signature = 'generations:sync {--stale-after=60 : Seconds without an update before a generation is polled}';

    protected $description = 'Poll providers for pending generations whose webhooks have not arrived';

    public function handle(ProviderRegistry $registry, ApplyProviderUpdate $apply): int
    {
        $staleBefore = now()->subSeconds((int) $this->option('stale-after'));

        Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('external_id')
            ->where('updated_at', '<', $staleBefore)
            ->each(function (Generation $generation) use ($registry, $apply): void {
                try {
                    $update = $registry->generator($generation->provider)->generation($generation->external_id);
                } catch (ProviderException $exception) {
                    $this->warn("{$generation->getKey()}: {$exception->getMessage()}");

                    return;
                }

                $apply->apply($generation, $update, type: 'provider.poll');
                $generation->touch();
            });

        return self::SUCCESS;
    }
}
