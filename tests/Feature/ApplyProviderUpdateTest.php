<?php

use App\AI\Providers\PixelForge\PixelForgeGenerationMapper;
use App\Billing\Transactions;
use App\Data\GenerationRequest;
use App\Data\ProviderWebhookEvent;
use App\Enums\GenerationStatus;
use App\Events\ProviderWebhookReceived;
use App\Jobs\StoreGenerationOutputs;
use App\Listeners\ApplyProviderWebhook;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    usePixelForgeTestConfig();
    Queue::fake();

    $this->generation = Generation::factory()->create(['external_id' => 'gen_123', 'fee_basis_points' => 2500, 'quote_total' => 467_343_750]);
    $this->transactions = app(Transactions::class);
    $this->transactions->topUp($this->generation->user, 1_000_000_000);
    $this->generation->update(['hold_transaction_id' => $this->transactions->hold($this->generation->user, 467_343_750, $this->generation)->id]);
});

function deliverWebhook(string $eventId, array $generation): void
{
    app(ApplyProviderWebhook::class)->handle(new ProviderWebhookReceived('pixelforge', new ProviderWebhookEvent(
        id: $eventId,
        type: 'generation.'.$generation['status'],
        occurredAt: CarbonImmutable::now(),
        generation: app(PixelForgeGenerationMapper::class)->map(pixelForgeGenerationResponse($generation)),
    )));
}

function succeededGeneration(): array
{
    return [
        'status' => 'succeeded',
        'image_url' => 'https://cdn.pixelforge.test/gen_123.png',
        'image_url_expires_at' => now()->addDay()->toIso8601String(),
        'usage' => ['input_text_tokens' => 100, 'input_image_tokens' => 0, 'output_tokens' => 6450, 'reasoning_tokens' => 8000, 'total_tokens' => 14550],
        'completed_at' => now()->toIso8601String(),
    ];
}

test('a processing webhook advances the generation and records a message', function () {
    deliverWebhook('evt_1', ['status' => 'processing']);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Processing)
        ->and($this->generation->fresh()->started_at)->not->toBeNull()
        ->and($this->generation->events()->pluck('message')->all())->toBe(['Rendering']);
});

test('a duplicate webhook delivery is applied once', function () {
    deliverWebhook('evt_1', ['status' => 'processing']);
    deliverWebhook('evt_1', ['status' => 'processing']);

    expect($this->generation->events()->count())->toBe(1);
});

test('a succeeded webhook records usage, charges the actual cost and queues the image download', function () {
    deliverWebhook('evt_2', succeededGeneration());

    $generation = $this->generation->fresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->output_tokens)->toBe(6450)
        ->and($generation->cost_total)->toBe(358_500_000)
        ->and($generation->fee_total)->toBe(89_625_000)
        ->and($generation->charge_total)->toBe(448_125_000)
        ->and($generation->rates['output_tokens'])->toBe('40.000000000')
        ->and($this->transactions->balance($generation->user))->toBe(1_000_000_000 - 448_125_000);

    Queue::assertPushed(StoreGenerationOutputs::class, fn (StoreGenerationOutputs $job): bool => $job->outputs[0]->url === 'https://cdn.pixelforge.test/gen_123.png');
});

test('a failed webhook records the error and releases the hold', function () {
    deliverWebhook('evt_3', ['status' => 'failed', 'error' => ['code' => 'generation_failed', 'message' => 'Could not render.']]);

    $generation = $this->generation->fresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_message)->toBe('Could not render.')
        ->and($this->transactions->balance($generation->user))->toBe(1_000_000_000);
    Queue::assertNothingPushed();
});

test('a late processing webhook does not move a finished generation backwards', function () {
    deliverWebhook('evt_2', succeededGeneration());
    deliverWebhook('evt_1', ['status' => 'processing']);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

test('webhooks link by the echoed generation id before the provider id is stored', function () {
    $this->generation->update(['external_id' => null]);

    deliverWebhook('evt_1', ['id' => 'gen_new', 'status' => 'processing', 'metadata' => [GenerationRequest::GENERATION_ID_METADATA_KEY => $this->generation->id]]);

    expect($this->generation->fresh()->external_id)->toBe('gen_new')
        ->and($this->generation->fresh()->status)->toBe(GenerationStatus::Processing);
});

test('webhooks for unknown generations are ignored', function () {
    deliverWebhook('evt_1', ['id' => 'gen_unknown', 'status' => 'processing']);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

test('webhooks from an earlier attempt do not affect a retried generation', function () {
    $this->generation->update(['attempt' => 2, 'external_id' => 'gen_retry']);

    deliverWebhook('evt_old', ['id' => 'gen_123', 'status' => 'failed', 'error' => ['code' => 'generation_failed', 'message' => 'Old attempt failed.'], 'metadata' => [
        GenerationRequest::GENERATION_ID_METADATA_KEY => $this->generation->id,
        GenerationRequest::ATTEMPT_METADATA_KEY => '1',
    ]]);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued)
        ->and($this->generation->events()->count())->toBe(0);
});
