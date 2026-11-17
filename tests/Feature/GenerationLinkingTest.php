<?php

use App\Data\GenerationRequest;
use App\Data\ProviderGeneration;
use App\Data\ReferenceAsset;
use App\Enums\GenerationStatus;
use App\Models\Generation;

test('provider requests carry the generation id as idempotency key and metadata', function () {
    $generation = Generation::factory()->create(['options' => ['size' => '1536x1024']]);
    $references = [new ReferenceAsset('https://trance.test/signed', 'image/png')];

    $request = $generation->toProviderRequest($references);

    expect($request->idempotencyKey)->toBe($generation->id)
        ->and($request->metadata)->toBe([
            GenerationRequest::GENERATION_ID_METADATA_KEY => $generation->id,
            GenerationRequest::ATTEMPT_METADATA_KEY => '1',
        ])
        ->and($request->generationId())->toBe($generation->id)
        ->and($request->prompt)->toBe($generation->prompt)
        ->and($request->options)->toBe(['size' => '1536x1024'])
        ->and($request->references)->toBe($references);
});

test('retried attempts use a new idempotency key while keeping the generation id', function () {
    $generation = Generation::factory()->create(['attempt' => 2]);

    $request = $generation->toProviderRequest();

    expect($request->idempotencyKey)->toBe("{$generation->id}:2")
        ->and($request->generationId())->toBe($generation->id)
        ->and($request->metadata[GenerationRequest::ATTEMPT_METADATA_KEY])->toBe('2');
});

test('provider generations resolve to local generations by external id', function () {
    $generation = Generation::factory()->create(['external_id' => 'gen_known']);

    $resolved = Generation::resolveFromProvider('pixelforge', new ProviderGeneration('gen_known', GenerationStatus::Processing));

    expect($resolved?->is($generation))->toBeTrue();
});

test('provider generations fall back to the echoed generation id before the external id is stored', function () {
    $generation = Generation::factory()->create();

    $resolved = Generation::resolveFromProvider('pixelforge', new ProviderGeneration(
        'gen_new',
        GenerationStatus::Processing,
        metadata: [GenerationRequest::GENERATION_ID_METADATA_KEY => $generation->id],
    ));

    expect($resolved?->is($generation))->toBeTrue();
});

test('provider generations do not resolve across providers or for unknown ids', function (string $provider, array $metadata) {
    $generation = Generation::factory()->create(['external_id' => 'gen_known']);
    $metadata = array_map(fn ($value) => $value === 'local' ? $generation->id : $value, $metadata);

    expect(Generation::resolveFromProvider($provider, new ProviderGeneration('gen_other', GenerationStatus::Queued, metadata: $metadata)))->toBeNull();
})->with([
    'other provider with matching id' => ['other-provider', [GenerationRequest::GENERATION_ID_METADATA_KEY => 'local']],
    'unknown external id without metadata' => ['pixelforge', []],
    'unknown generation id' => ['pixelforge', [GenerationRequest::GENERATION_ID_METADATA_KEY => '00000000-0000-4000-8000-000000000000']],
]);
