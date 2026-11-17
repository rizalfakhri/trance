<?php

use App\Data\GeneratedAsset;
use App\Enums\Modality;
use App\Exceptions\Generations\AssetDownloadException;
use App\Generations\GenerationStorage;
use App\Generations\GenerationTimeline;
use App\Jobs\StoreGenerationOutputs;
use App\Models\Generation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->generation = Generation::factory()->succeeded()->create();
});

function generatedOutput(string $url, ?DateTimeInterface $expiresAt = null): GeneratedAsset
{
    return new GeneratedAsset(Modality::Image, $url, 'image/png', expiresAt: $expiresAt === null ? now()->toImmutable()->addDay() : $expiresAt);
}

test('every output is stored in order', function () {
    Http::fake(['cdn.provider.test/*' => Http::response(UploadedFile::fake()->image('o.png', 4, 4)->getContent(), 200, ['Content-Type' => 'image/png'])]);

    $job = (new StoreGenerationOutputs($this->generation, [
        generatedOutput('https://cdn.provider.test/a.png'),
        generatedOutput('https://cdn.provider.test/b.png'),
    ]))->withFakeQueueInteractions();

    $job->handle(app(GenerationStorage::class), app(GenerationTimeline::class));

    $job->assertNotFailed();
    expect($this->generation->outputs()->pluck('source_url')->all())->toBe(['https://cdn.provider.test/a.png', 'https://cdn.provider.test/b.png']);
});

test('download failures are thrown so the queue retries the job', function () {
    Http::fake(['cdn.provider.test/*' => Http::response('', 500)]);

    $job = new StoreGenerationOutputs($this->generation, [generatedOutput('https://cdn.provider.test/a.png')]);

    $job->handle(app(GenerationStorage::class), app(GenerationTimeline::class));
})->throws(AssetDownloadException::class);

test('outputs that expired before being stored fail the job without retrying', function () {
    Http::fake();

    $job = (new StoreGenerationOutputs($this->generation, [
        generatedOutput('https://cdn.provider.test/a.png', now()->toImmutable()->subMinute()),
    ]))->withFakeQueueInteractions();

    $job->handle(app(GenerationStorage::class), app(GenerationTimeline::class));

    $job->assertFailedWith(AssetDownloadException::class);
    Http::assertNothingSent();
});

test('the job is unique per generation', function () {
    expect((new StoreGenerationOutputs($this->generation, []))->uniqueId())->toBe($this->generation->id);
});
