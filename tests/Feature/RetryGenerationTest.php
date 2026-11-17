<?php

use App\Billing\Transactions;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    usePixelForgeTestConfig();
    Http::fake([PIXELFORGE_TEST_URL.'/pricing' => Http::response(pixelForgePricingResponse())]);
    Queue::fake();

    $this->generation = Generation::factory()->failed()->create([
        'prompt' => str_repeat('a', 400),
        'options' => ['size' => '1024x1024', 'quality' => 'medium'],
    ]);
    $this->transactions = app(Transactions::class);
});

test('a failed generation is retried as a new attempt with a fresh hold', function () {
    $this->transactions->topUp($this->generation->user, 1_000_000_000);

    $this->actingAs($this->generation->user)
        ->post(route('generations.retry', $this->generation))
        ->assertRedirect(route('generations.show', $this->generation));

    $generation = $this->generation->fresh();

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->attempt)->toBe(2)
        ->and($generation->external_id)->toBeNull()
        ->and($generation->error_message)->toBeNull()
        ->and($generation->hold->amount)->toBe(-$generation->quote_total)
        ->and($this->transactions->balance($generation->user))->toBe(1_000_000_000 - $generation->quote_total)
        ->and($generation->events()->pluck('message')->all())->toContain('Retrying (attempt 2)');

    Queue::assertPushed(SubmitGeneration::class);
});

test('only failed generations can be retried', function () {
    $generation = Generation::factory()->create();
    $this->transactions->topUp($generation->user, 1_000_000_000);

    $this->actingAs($generation->user)
        ->post(route('generations.retry', $generation))
        ->assertSessionHasErrors('generation');

    expect($generation->fresh()->attempt)->toBe(1);
    Queue::assertNothingPushed();
});

test('retrying without enough balance is refused and leaves the generation failed', function () {
    $this->actingAs($this->generation->user)
        ->post(route('generations.retry', $this->generation))
        ->assertSessionHasErrors('balance');

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($this->generation->fresh()->attempt)->toBe(1);
    Queue::assertNothingPushed();
});

test('other users cannot retry a generation', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('generations.retry', $this->generation))
        ->assertNotFound();
});

test('generations of a disabled provider cannot be retried', function () {
    config(['generation.disabled_providers' => ['pixelforge']]);
    $this->transactions->topUp($this->generation->user, 1_000_000_000);

    $this->actingAs($this->generation->user)
        ->post(route('generations.retry', $this->generation))
        ->assertSessionHasErrors('generation');

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Failed);
    Queue::assertNothingPushed();
});
