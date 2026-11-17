<?php

namespace App\Http\Resources;

use App\Generations\GenerationStorage;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GalleryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $output = $this->outputs->first();

        return [
            'id' => $this->id,
            'url' => route('generations.show', $this->resource),
            'status' => $this->status->value,
            'prompt' => $this->prompt,
            'thumbnailUrl' => $output === null ? null : app(GenerationStorage::class)->temporaryUrl($output),
            'aspectRatio' => $this->aspectRatio($output?->width, $output?->height),
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }

    private function aspectRatio(?int $width, ?int $height): string
    {
        if ($width && $height) {
            return "{$width} / {$height}";
        }

        $size = $this->options['size'] ?? null;

        return is_string($size) && preg_match('/^(\d+)x(\d+)$/', $size, $matches) ? "{$matches[1]} / {$matches[2]}" : '1 / 1';
    }
}
