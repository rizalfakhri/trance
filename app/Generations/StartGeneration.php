<?php

namespace App\Generations;

use App\Billing\Transactions;
use App\Data\GenerationRequest;
use App\Enums\GenerationStatus;
use App\Exceptions\Billing\InsufficientBalanceException;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pricing\GenerationQuoter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class StartGeneration
{
    public function __construct(
        private GenerationQuoter $quoter,
        private Transactions $transactions,
        private GenerationStorage $storage,
        private GenerationTimeline $timeline,
    ) {}

    /**
     * Quotes the request, holds the worst case charge, stores the references and queues the
     * provider submission.
     *
     * @param  list<UploadedFile>  $referenceFiles
     * @param  list<string>  $referenceUrls
     *
     * @throws InsufficientBalanceException
     */
    public function handle(User $user, string $provider, GenerationRequest $request, array $referenceFiles = [], array $referenceUrls = []): Generation
    {
        $charge = $this->quoter->quote($provider, $request);

        $generation = DB::transaction(function () use ($user, $provider, $request, $charge, $referenceUrls): Generation {
            $generation = Generation::query()->create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'model' => $request->model,
                'modality' => $request->modality,
                'prompt' => $request->prompt,
                'options' => $request->options,
                'reference_urls' => $referenceUrls === [] ? null : $referenceUrls,
                'status' => GenerationStatus::Queued,
                'quote_total' => $charge->total(),
                'fee_basis_points' => $charge->feeBasisPoints,
                'submitted_at' => now(),
            ]);

            $hold = $this->transactions->hold($user, $charge->total(), $generation, 'Generation hold');
            $generation->update(['hold_transaction_id' => $hold->getKey()]);

            return $generation;
        });

        try {
            foreach ($referenceFiles as $position => $file) {
                $this->storage->storeReference($generation, $file, $position);
            }
        } catch (Throwable $exception) {
            $this->transactions->release($generation->hold);
            $generation->update([
                'status' => GenerationStatus::Failed,
                'error_code' => 'reference_storage_failed',
                'error_message' => 'Reference images could not be stored.',
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $this->timeline->record($generation, 'created', 'Request received', GenerationStatus::Queued);

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
