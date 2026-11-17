<?php

namespace App\Generations;

use App\Billing\Transactions;
use App\Data\ReferenceAsset;
use App\Enums\GenerationStatus;
use App\Exceptions\Billing\InsufficientBalanceException;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\GenerationAsset;
use App\Pricing\GenerationQuoter;
use App\Registries\ProviderRegistry;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RetryGeneration
{
    public function __construct(
        private GenerationQuoter $quoter,
        private Transactions $transactions,
        private GenerationTimeline $timeline,
        private ProviderRegistry $registry,
    ) {}

    /**
     * Starts a new provider attempt for a failed generation: it is quoted again, a fresh hold is
     * placed and the attempt number moves on, which gives the provider a new idempotency key.
     *
     * @throws InsufficientBalanceException
     * @throws LogicException when the generation is not failed
     */
    public function handle(Generation $generation): Generation
    {
        if (! $this->registry->isEnabled($generation->provider)) {
            throw new LogicException('This provider is currently unavailable.');
        }

        $references = [
            ...$generation->references()->get()->map(fn (GenerationAsset $asset): ReferenceAsset => new ReferenceAsset('', $asset->mime_type))->all(),
            ...$generation->referenceUrlAssets(),
        ];
        $charge = $this->quoter->quote($generation->provider, $generation->toProviderRequest($references));

        $generation = DB::transaction(function () use ($generation, $charge): Generation {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->getKey());

            if ($generation->status !== GenerationStatus::Failed) {
                throw new LogicException('Only failed generations can be retried.');
            }

            $hold = $this->transactions->hold($generation->user, $charge->total(), $generation, 'Generation hold');

            $generation->update([
                'status' => GenerationStatus::Queued,
                'attempt' => $generation->attempt + 1,
                'external_id' => null,
                'quote_total' => $charge->total(),
                'fee_basis_points' => $charge->feeBasisPoints,
                'hold_transaction_id' => $hold->getKey(),
                'error_code' => null,
                'error_message' => null,
                'submitted_at' => now(),
                'started_at' => null,
                'completed_at' => null,
            ]);

            $this->timeline->record($generation, 'retried', "Retrying (attempt {$generation->attempt})", GenerationStatus::Queued);

            return $generation;
        });

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
