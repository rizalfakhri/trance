<?php

namespace App\Generations;

use App\Billing\Transactions;
use App\Contracts\Providers\PricingCatalog;
use App\Data\GenerationCost;
use App\Data\ProviderGeneration;
use App\Enums\GenerationStatus;
use App\Jobs\StoreGenerationOutputs;
use App\Models\Generation;
use App\Models\GenerationEvent;
use App\Pricing\PercentageMarkup;
use App\Registries\ProviderRegistry;
use Illuminate\Support\Facades\DB;

final readonly class ApplyProviderUpdate
{
    public function __construct(
        private Transactions $transactions,
        private ProviderRegistry $registry,
        private GenerationTimeline $timeline,
    ) {}

    /**
     * Applies a provider state to the local generation. Duplicate provider events are ignored and
     * a generation never moves back to an earlier status (webhooks may arrive late or out of order).
     */
    public function apply(Generation $generation, ProviderGeneration $update, ?string $providerEventId = null, string $type = 'provider.update'): void
    {
        $completed = DB::transaction(function () use ($generation, $update, $providerEventId, $type): ?Generation {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->getKey());

            if ($providerEventId !== null && GenerationEvent::query()->where('provider_event_id', $providerEventId)->exists()) {
                return null;
            }

            if ($update->attempt() !== null && $update->attempt() !== $generation->attempt) {
                return null;
            }

            $generation->external_id ??= $update->externalId;

            if (! $this->advances($generation->status, $update->status)) {
                $generation->save();

                if ($providerEventId !== null) {
                    $this->timeline->record($generation, $type, null, $update->status, $providerEventId);
                }

                return null;
            }

            $generation->status = $update->status;
            $generation->started_at ??= $update->startedAt ?? ($update->status === GenerationStatus::Processing ? now() : null);

            match ($update->status) {
                GenerationStatus::Succeeded => $this->settleSucceeded($generation, $update),
                GenerationStatus::Failed => $this->settleFailed($generation, $update->error?->code ?? 'generation_failed', $update->error?->message ?? 'The generation failed.'),
                default => null,
            };

            $generation->save();
            $this->timeline->record($generation, $type, $this->message($generation, $update), $update->status, $providerEventId);

            return $update->status === GenerationStatus::Succeeded ? $generation : null;
        });

        if ($completed !== null && $update->outputs !== []) {
            $this->timeline->record($completed, 'outputs.storing', 'Saving image');
            StoreGenerationOutputs::dispatch($completed, $update->outputs);
        }
    }

    public function fail(Generation $generation, string $code, string $message): void
    {
        DB::transaction(function () use ($generation, $code, $message): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->getKey());

            if ($generation->status->isTerminal()) {
                return;
            }

            $generation->status = GenerationStatus::Failed;
            $this->settleFailed($generation, $code, $message);
            $generation->save();
            $this->timeline->record($generation, 'failed', $message, GenerationStatus::Failed);
        });
    }

    private function advances(GenerationStatus $current, GenerationStatus $next): bool
    {
        return ! $current->isTerminal() && $this->rank($next) > $this->rank($current);
    }

    private function rank(GenerationStatus $status): int
    {
        return match ($status) {
            GenerationStatus::Queued => 0,
            GenerationStatus::Processing => 1,
            GenerationStatus::Succeeded, GenerationStatus::Failed => 2,
        };
    }

    private function settleSucceeded(Generation $generation, ProviderGeneration $update): void
    {
        $generation->completed_at = $update->completedAt ?? now();

        if ($update->usage === null) {
            $this->releaseHold($generation);

            return;
        }

        $pricing = $update->pricing ?? $this->registry->pricing($generation->provider)->pricing($generation->toProviderRequest());
        $cost = GenerationCost::calculate($update->usage, $pricing);
        $charge = (new PercentageMarkup((int) $generation->fee_basis_points))->apply($cost, $generation->provider);

        $generation->fill([
            'input_text_tokens' => $update->usage->inputTextTokens(),
            'input_image_tokens' => $update->usage->inputImageTokens(),
            'output_tokens' => $update->usage->outputTokens(),
            'reasoning_tokens' => $update->usage->reasoningTokens(),
            'additional_units' => $update->usage->additionalUnits(),
            'rates' => $this->rateSnapshot($pricing),
            'cost_total' => $cost->total(),
            'fee_total' => $charge->fee,
            'charge_total' => $charge->total(),
        ]);

        if ($generation->hold !== null) {
            $this->transactions->settle($generation->hold, $charge->total(), 'Generation');
        }
    }

    private function settleFailed(Generation $generation, string $code, string $message): void
    {
        $generation->fill([
            'error_code' => $code,
            'error_message' => $message,
            'completed_at' => now(),
        ]);

        $this->releaseHold($generation);
    }

    private function releaseHold(Generation $generation): void
    {
        if ($generation->hold !== null) {
            $this->transactions->release($generation->hold);
        }
    }

    /**
     * @return array<string, string>
     */
    private function rateSnapshot(PricingCatalog $pricing): array
    {
        return [
            'currency' => $pricing->currency(),
            'input_text_tokens' => $pricing->inputTextTokenRate()->toDecimal(),
            'input_image_tokens' => $pricing->inputImageTokenRate()->toDecimal(),
            'output_tokens' => $pricing->outputTokenRate()->toDecimal(),
            'reasoning_tokens' => $pricing->reasoningTokenRate()->toDecimal(),
        ];
    }

    private function message(Generation $generation, ProviderGeneration $update): string
    {
        return match ($update->status) {
            GenerationStatus::Queued => 'Queued at provider',
            GenerationStatus::Processing => 'Rendering',
            GenerationStatus::Succeeded => 'Image ready',
            GenerationStatus::Failed => (string) $generation->error_message,
        };
    }
}
