<?php

namespace App\Jobs;

use App\Exceptions\Providers\ProviderException;
use App\Exceptions\Providers\ProviderUnavailableException;
use App\Exceptions\Providers\RateLimitedException;
use App\Generations\ApplyProviderUpdate;
use App\Generations\GenerationStorage;
use App\Generations\GenerationTimeline;
use App\Models\Generation;
use App\Registries\ProviderRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitGeneration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const DEFAULT_RETRY_SECONDS = 10;

    public int $tries = 10;

    public int $timeout = 240;

    public function __construct(public Generation $generation) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60];
    }

    public function uniqueId(): string
    {
        return $this->generation->getKey();
    }

    public function handle(ProviderRegistry $registry, GenerationStorage $storage, ApplyProviderUpdate $apply, GenerationTimeline $timeline): void
    {
        $this->generation->refresh();

        if ($this->generation->status->isTerminal() || $this->generation->external_id !== null) {
            return;
        }

        try {
            $result = $registry->generator($this->generation->provider)
                ->generate($this->generation->toProviderRequest([
                    ...$storage->referenceAssets($this->generation),
                    ...$this->generation->referenceUrlAssets(),
                ]));
        } catch (RateLimitedException|ProviderUnavailableException $exception) {
            $this->release($exception->retryAfterSeconds ?? self::DEFAULT_RETRY_SECONDS);

            return;
        } catch (ProviderException $exception) {
            $apply->fail($this->generation, 'submission_rejected', $exception->getMessage());

            return;
        }

        $timeline->record($this->generation, 'submitted', 'Submitted to '.$registry->get($this->generation->provider)->label());
        $apply->apply($this->generation, $result, type: 'submitted');
    }

    public function failed(?Throwable $exception): void
    {
        app(ApplyProviderUpdate::class)->fail($this->generation, 'submission_failed', 'The provider could not be reached.');
    }
}
