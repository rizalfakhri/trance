<?php

namespace App\Http\Resources;

use App\Contracts\Providers\Capabilities\ModelCapabilities;
use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Exceptions\Providers\ProviderException;
use App\Generations\GenerationStorage;
use App\Models\Generation;
use App\Models\GenerationAsset;
use App\Models\GenerationEvent;
use App\Registries\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    private const VISIBLE_EVENTS = 30;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storage = app(GenerationStorage::class);
        $model = $this->modelCapabilities();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'provider' => [
                'name' => $this->provider,
                'label' => $this->providerLabel(),
                'logoUrl' => $this->providerCapabilities()?->logoUrl(),
                'darkLogoUrl' => $this->providerCapabilities()?->darkLogoUrl(),
            ],
            'model' => $this->model === null ? null : [
                'id' => $this->model,
                'label' => $model?->label() ?? $this->model,
            ],
            'prompt' => $this->prompt,
            'options' => $this->optionSummary($model),
            'expectedSize' => $this->expectedSize(),
            'cost' => $this->costSummary(),
            'error' => $this->error_code === null ? null : ['code' => $this->error_code, 'message' => $this->error_message],
            'attempt' => $this->attempt,
            'createdAt' => ($this->submitted_at ?? $this->created_at)->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'references' => [
                ...$this->assets
                    ->where('role.value', 'reference')
                    ->sortBy('position')
                    ->map(fn (GenerationAsset $asset): array => ['id' => "upload-{$asset->id}", 'url' => $storage->temporaryUrl($asset)])
                    ->values()
                    ->all(),
                ...array_map(fn (string $url, int $index): array => ['id' => "url-{$index}", 'url' => $url], $this->reference_urls ?? [], array_keys($this->reference_urls ?? [])),
            ],
            'outputs' => $this->assets
                ->where('role.value', 'output')
                ->sortBy('position')
                ->map(fn (GenerationAsset $asset): array => [
                    'id' => $asset->id,
                    'url' => $storage->temporaryUrl($asset),
                    'downloadUrl' => route('generations.outputs.download', [$this->resource, $asset]),
                    'mimeType' => $asset->mime_type,
                    'width' => $asset->width,
                    'height' => $asset->height,
                ])
                ->values()
                ->all(),
            'events' => $this->events
                ->whereNotNull('message')
                ->sortBy('occurred_at')
                ->take(-self::VISIBLE_EVENTS)
                ->map(fn (GenerationEvent $event): array => [
                    'id' => $event->id,
                    'status' => $event->status?->value,
                    'message' => $event->message,
                    'offsetMs' => max(0, (int) ($this->submitted_at ?? $this->created_at)->diffInMilliseconds($event->occurred_at, false)),
                ])
                ->values()
                ->all(),
        ];
    }

    private function modelCapabilities(): ?ModelCapabilities
    {
        $registry = app(ProviderRegistry::class);

        if (! $registry->has($this->provider)) {
            return null;
        }

        try {
            return $registry->get($this->provider)->capabilities()->model($this->model);
        } catch (ProviderException) {
            return null;
        }
    }

    private function providerCapabilities(): ?ProviderCapabilities
    {
        $registry = app(ProviderRegistry::class);

        return $registry->has($this->provider) ? $registry->get($this->provider)->capabilities() : null;
    }

    private function providerLabel(): string
    {
        $registry = app(ProviderRegistry::class);

        return $registry->has($this->provider) ? $registry->get($this->provider)->label() : $this->provider;
    }

    /**
     * @return list<array{key: string, label: string, value: string}>
     */
    private function optionSummary(?ModelCapabilities $model): array
    {
        $summary = [];

        foreach ($this->options as $key => $value) {
            $option = $model?->option($key);
            $choice = collect($option?->choices() ?? [])->first(fn ($choice): bool => (string) $choice->value() === (string) $value);

            $summary[] = [
                'key' => $key,
                'label' => $option?->label() ?? $key,
                'value' => $choice?->label() ?? (is_bool($value) ? ($value ? 'On' : 'Off') : (string) $value),
            ];
        }

        return $summary;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private function expectedSize(): ?array
    {
        $size = $this->options['size'] ?? null;

        if (! is_string($size) || ! preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
            return null;
        }

        return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
    }
}
