<?php

use App\Data\GeneratedAsset;
use App\Enums\AssetRole;
use App\Enums\Modality;
use App\Exceptions\Generations\AssetDownloadException;
use App\Generations\GenerationStorage;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local', ['serve' => true]);
    config(['generation.storage.max_output_bytes' => 1024 * 1024]);

    $this->storage = app(GenerationStorage::class);
    $this->generation = Generation::factory()->create();
});

function fakePng(int $width = 32, int $height = 16): string
{
    return UploadedFile::fake()->image('output.png', $width, $height)->getContent();
}

function outputAsset(string $url = 'https://cdn.provider.test/out.png'): GeneratedAsset
{
    return new GeneratedAsset(Modality::Image, $url, 'image/png', 1536, 1024, expiresAt: CarbonImmutable::parse('2026-11-18T10:00:00Z'));
}

test('reference uploads are stored privately with their dimensions', function () {
    $asset = $this->storage->storeReference($this->generation, UploadedFile::fake()->image('style.png', 64, 48), 2);

    expect($asset->role)->toBe(AssetRole::Reference)
        ->and($asset->path)->toBe("generations/{$this->generation->id}/references/2.png")
        ->and($asset->mime_type)->toBe('image/png')
        ->and([$asset->width, $asset->height])->toBe([64, 48]);

    Storage::disk('local')->assertExists($asset->path);
});

test('outputs are downloaded and recorded with their provider source', function () {
    Http::fake(['cdn.provider.test/*' => Http::response(fakePng(32, 16), 200, ['Content-Type' => 'image/png'])]);

    $asset = $this->storage->storeOutput($this->generation, outputAsset(), 0);

    expect($asset->role)->toBe(AssetRole::Output)
        ->and($asset->path)->toBe("generations/{$this->generation->id}/outputs/0.png")
        ->and([$asset->width, $asset->height])->toBe([32, 16])
        ->and($asset->source_url)->toBe('https://cdn.provider.test/out.png')
        ->and($asset->source_expires_at->toIso8601ZuluString())->toBe('2026-11-18T10:00:00Z');

    Storage::disk('local')->assertExists($asset->path);
});

test('storing the same output twice downloads it once', function () {
    Http::fake(['cdn.provider.test/*' => Http::response(fakePng(), 200, ['Content-Type' => 'image/png'])]);

    $first = $this->storage->storeOutput($this->generation, outputAsset(), 0);
    $second = $this->storage->storeOutput($this->generation, outputAsset(), 0);

    expect($second->is($first))->toBeTrue()
        ->and($this->generation->outputs()->count())->toBe(1);
    Http::assertSentCount(1);
});

test('downloads that are not media, too large or failing are rejected and nothing is stored', function (Closure $response, string $message) {
    Http::fake(['cdn.provider.test/*' => $response]);

    try {
        $this->storage->storeOutput($this->generation, outputAsset(), 0);
        $this->fail('Expected the download to be rejected.');
    } catch (AssetDownloadException $exception) {
        expect($exception->getMessage())->toContain($message);
    }

    expect($this->generation->outputs()->exists())->toBeFalse();
    Storage::disk('local')->assertMissing("generations/{$this->generation->id}/outputs/0.png");
})->with([
    'html page' => [fn () => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']), 'not an image or video'],
    'too large' => [fn () => Http::response(str_repeat('a', 1024 * 1024 + 1), 200, ['Content-Type' => 'image/png']), 'byte limit'],
    'server error' => [fn () => Http::response('', 500), 'HTTP 500'],
]);

function useRealLocalDisk(): void
{
    config(['filesystems.disks.local.root' => storage_path('framework/testing/disks/generation-signed-urls')]);
    Storage::forgetDisk('local');
}

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/disks/generation-signed-urls'));
});

test('reference assets are exposed to providers as signed urls that serve the file', function () {
    useRealLocalDisk();
    $this->storage->storeReference($this->generation, UploadedFile::fake()->image('style.png', 8, 8), 0);

    $references = $this->storage->referenceAssets($this->generation);

    expect($references)->toHaveCount(1)
        ->and($references[0]->mimeType)->toBe('image/png')
        ->and($references[0]->url)->toContain('signature=');

    $this->get($references[0]->url)->assertOk();
});

test('signed reference urls stop working once they expire', function () {
    useRealLocalDisk();
    $this->storage->storeReference($this->generation, UploadedFile::fake()->image('style.png', 8, 8), 0);
    $url = $this->storage->referenceAssets($this->generation)[0]->url;

    $this->travel(61)->minutes();

    $this->get($url)->assertForbidden();
});

test('outputs returned inline as data urls are stored without downloading', function () {
    Http::fake();
    $png = fakePng(12, 8);

    $asset = $this->storage->storeOutput($this->generation, new GeneratedAsset(Modality::Image, 'data:image/png;base64,'.base64_encode($png), 'image/png'), 0);

    expect($asset->path)->toBe("generations/{$this->generation->id}/outputs/0.png")
        ->and([$asset->width, $asset->height])->toBe([12, 8])
        ->and($asset->source_url)->toBeNull();
    Storage::disk('local')->assertExists($asset->path);
    Http::assertNothingSent();
});
