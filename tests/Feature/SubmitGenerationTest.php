<?php

use App\Billing\Transactions;
use App\Enums\GenerationStatus;
use App\Generations\ApplyProviderUpdate;
use App\Generations\GenerationStorage;
use App\Generations\GenerationTimeline;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Registries\ProviderRegistry;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    usePixelForgeTestConfig();

    $this->generation = Generation::factory()->create();
    $this->transactions = app(Transactions::class);
    $this->transactions->topUp($this->generation->user, 1_000_000_000);
    $this->generation->update(['hold_transaction_id' => $this->transactions->hold($this->generation->user, 400_000_000, $this->generation)->id]);
});

function runSubmission(Generation $generation): SubmitGeneration
{
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
    $job->handle(app(ProviderRegistry::class), app(GenerationStorage::class), app(ApplyProviderUpdate::class), app(GenerationTimeline::class));

    return $job;
}

test('submitting stores the provider id and links the request to the generation', function () {
    Http::fake([PIXELFORGE_TEST_URL.'/generations' => Http::response(pixelForgeGenerationResponse(['id' => 'gen_remote']), 202)]);

    runSubmission($this->generation)->assertNotReleased();

    expect($this->generation->fresh()->external_id)->toBe('gen_remote')
        ->and($this->generation->events()->pluck('message')->all())->toContain('Submitted to PixelForge');

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Idempotency-Key', $this->generation->id)
        && $request['metadata'] === ['trance_generation_id' => $this->generation->id, 'trance_attempt' => '1']);
});

test('rate limited submissions are retried after the provider delay', function () {
    Http::fake([PIXELFORGE_TEST_URL.'/generations' => Http::response(['error' => ['code' => 'concurrency_limit_exceeded', 'message' => 'Busy']], 429, ['Retry-After' => '5'])]);

    runSubmission($this->generation)->assertReleased(delay: 5);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

test('rejected submissions fail the generation and release the hold', function () {
    Http::fake([PIXELFORGE_TEST_URL.'/generations' => Http::response(['error' => ['code' => 'content_policy_violation', 'message' => 'Prompt rejected']], 400)]);

    runSubmission($this->generation);

    $generation = $this->generation->fresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_message)->toBe('Prompt rejected')
        ->and($this->transactions->balance($generation->user))->toBe(1_000_000_000);
});

test('generations that were already submitted are not submitted again', function () {
    Http::fake();
    $this->generation->update(['external_id' => 'gen_existing']);

    runSubmission($this->generation);

    Http::assertNothingSent();
});

test('reference urls are sent to the provider as given', function () {
    Http::fake([PIXELFORGE_TEST_URL.'/generations' => Http::response(pixelForgeGenerationResponse(['id' => 'gen_remote']), 202)]);
    $this->generation->update(['reference_urls' => ['https://example.com/style.png']]);

    runSubmission($this->generation);

    Http::assertSent(fn (ClientRequest $request): bool => $request['reference_images'] === ['https://example.com/style.png']);
});
