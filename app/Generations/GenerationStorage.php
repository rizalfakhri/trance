<?php

namespace App\Generations;

use App\Data\GeneratedAsset;
use App\Data\ReferenceAsset;
use App\Enums\AssetRole;
use App\Exceptions\Generations\AssetDownloadException;
use App\Models\Generation;
use App\Models\GenerationAsset;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

final readonly class GenerationStorage
{
    public function __construct(
        #[Config('generation.storage.disk')] private string $disk,
        #[Config('generation.storage.max_output_bytes')] private int $maxOutputBytes,
        #[Config('generation.storage.reference_url_ttl')] private int $referenceUrlTtlMinutes,
    ) {}

    public function storeReference(Generation $generation, UploadedFile $file, int $position): GenerationAsset
    {
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $path = $file->storeAs($this->directory($generation, AssetRole::Reference), "{$position}.{$extension}", $this->disk);
        [$width, $height] = $this->imageDimensions((string) $file->getContent());

        return $generation->assets()->create([
            'role' => AssetRole::Reference,
            'position' => $position,
            'disk' => $this->disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function output(Generation $generation, int $position): ?GenerationAsset
    {
        return $generation->outputs()->where('position', $position)->first();
    }

    /**
     * @throws AssetDownloadException
     */
    public function storeOutput(Generation $generation, GeneratedAsset $asset, int $position): GenerationAsset
    {
        $existing = $this->output($generation, $position);

        if ($existing !== null) {
            return $existing;
        }

        [$mimeType, $contents] = str_starts_with($asset->url, 'data:')
            ? $this->decodeDataUrl($asset->url, $position)
            : $this->download($asset, $position);

        if (! Str::startsWith($mimeType, ['image/', 'video/'])) {
            throw new AssetDownloadException("Output {$position} is not an image or video ({$mimeType}).");
        }

        if (strlen($contents) > $this->maxOutputBytes) {
            throw new AssetDownloadException("Output {$position} exceeds the {$this->maxOutputBytes} byte limit.");
        }

        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'bin';
        $path = $this->directory($generation, AssetRole::Output)."/{$position}.{$extension}";

        Storage::disk($this->disk)->put($path, $contents);

        [$width, $height] = $this->imageDimensions($contents);

        return $generation->assets()->create([
            'role' => AssetRole::Output,
            'position' => $position,
            'disk' => $this->disk,
            'path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'width' => $width ?? $asset->width,
            'height' => $height ?? $asset->height,
            'duration_seconds' => $asset->durationSeconds,
            'source_url' => str_starts_with($asset->url, 'data:') ? null : $asset->url,
            'source_expires_at' => $asset->expiresAt,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws AssetDownloadException
     */
    private function download(GeneratedAsset $asset, int $position): array
    {
        try {
            $response = Http::connectTimeout(5)->timeout(60)->get($asset->url);
        } catch (ConnectionException $exception) {
            throw new AssetDownloadException("Output {$position} could not be reached.", previous: $exception);
        }

        if (! $response->successful()) {
            throw new AssetDownloadException("Output {$position} download failed with HTTP {$response->status()}.");
        }

        return [Str::before((string) $response->header('Content-Type'), ';') ?: $asset->mimeType, $response->body()];
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws AssetDownloadException
     */
    private function decodeDataUrl(string $url, int $position): array
    {
        if (! preg_match('/^data:([\w.+\/-]+);base64,(.*)$/s', $url, $matches)) {
            throw new AssetDownloadException("Output {$position} is not a valid data URL.");
        }

        $contents = base64_decode($matches[2], true);

        if ($contents === false) {
            throw new AssetDownloadException("Output {$position} has invalid base64 data.");
        }

        return [$matches[1], $contents];
    }

    public function temporaryUrl(GenerationAsset $asset, ?int $minutes = null): string
    {
        return Storage::disk($asset->disk)->temporaryUrl($asset->path, now()->addMinutes($minutes ?? $this->referenceUrlTtlMinutes));
    }

    /**
     * @return list<ReferenceAsset>
     */
    public function referenceAssets(Generation $generation): array
    {
        return $generation->references()->get()
            ->map(fn (GenerationAsset $asset): ReferenceAsset => new ReferenceAsset($this->temporaryUrl($asset), $asset->mime_type))
            ->values()
            ->all();
    }

    private function directory(Generation $generation, AssetRole $role): string
    {
        return "generations/{$generation->getKey()}/{$role->value}s";
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageDimensions(string $contents): array
    {
        $size = $contents === '' ? false : @getimagesizefromstring($contents);

        return $size === false ? [null, null] : [$size[0], $size[1]];
    }
}
