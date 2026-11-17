<?php

namespace App\Jobs;

use App\Data\GeneratedAsset;
use App\Exceptions\Generations\AssetDownloadException;
use App\Generations\GenerationStorage;
use App\Generations\GenerationTimeline;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreGenerationOutputs implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  list<GeneratedAsset>  $outputs
     */
    public function __construct(
        public Generation $generation,
        public array $outputs,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function uniqueId(): string
    {
        return $this->generation->getKey();
    }

    public function handle(GenerationStorage $storage, GenerationTimeline $timeline): void
    {
        foreach ($this->outputs as $position => $output) {
            if ($output->expiresAt?->isPast() && $storage->output($this->generation, $position) === null) {
                $this->fail(new AssetDownloadException("Output {$position} expired before it could be stored."));

                return;
            }

            $storage->storeOutput($this->generation, $output, $position);
        }

        $timeline->record($this->generation, 'outputs.stored', count($this->outputs) === 1 ? 'Image saved' : 'Images saved');
    }
}
