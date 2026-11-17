<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    usePixelForgeTestConfig();
});

test('stale pending generations are polled and updated', function () {
    Http::fake([PIXELFORGE_TEST_URL.'/generations/gen_stale' => Http::response(pixelForgeGenerationResponse(['id' => 'gen_stale', 'status' => 'processing']))]);
    $generation = Generation::factory()->create(['external_id' => 'gen_stale', 'updated_at' => now()->subMinutes(5)]);

    $this->artisan('generations:sync')->assertSuccessful();

    expect($generation->fresh()->status)->toBe(GenerationStatus::Processing);
});

test('recently updated and finished generations are not polled', function () {
    Http::fake();
    Generation::factory()->create(['external_id' => 'gen_fresh']);
    Generation::factory()->succeeded()->create(['updated_at' => now()->subMinutes(5)]);

    $this->artisan('generations:sync')->assertSuccessful();

    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/generations/'));
});
