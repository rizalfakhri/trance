<?php

namespace App\AI\Providers\PixelForge;

use App\Data\GeneratedAsset;
use App\Data\ProviderError;
use App\Data\ProviderGeneration;
use App\Data\TokenUsage;
use App\Enums\GenerationStatus;
use App\Enums\Modality;
use Carbon\CarbonImmutable;

final class PixelForgeGenerationMapper
{
    private const STATUSES = [
        'queued' => GenerationStatus::Queued,
        'processing' => GenerationStatus::Processing,
        'succeeded' => GenerationStatus::Succeeded,
        'failed' => GenerationStatus::Failed,
    ];

    /**
     * @param  array<string, mixed>  $generation
     */
    public function map(array $generation): ProviderGeneration
    {
        return new ProviderGeneration(
            externalId: $generation['id'],
            status: self::STATUSES[$generation['status']],
            outputs: $this->outputs($generation),
            usage: $this->usage($generation['usage'] ?? null),
            pricing: isset($generation['rates']) ? PixelForgeRateCard::catalogFromRates($generation['rates']) : null,
            error: isset($generation['error']) ? new ProviderError($generation['error']['code'], $generation['error']['message']) : null,
            startedAt: $this->date($generation['started_at'] ?? null),
            completedAt: $this->date($generation['completed_at'] ?? null),
            metadata: $generation['metadata'] ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $generation
     * @return list<GeneratedAsset>
     */
    private function outputs(array $generation): array
    {
        if (empty($generation['image_url'])) {
            return [];
        }

        [$width, $height] = array_map(intval(...), explode('x', $generation['size']));

        return [new GeneratedAsset(
            modality: Modality::Image,
            url: $generation['image_url'],
            mimeType: 'image/png',
            width: $width,
            height: $height,
            expiresAt: $this->date($generation['image_url_expires_at'] ?? null),
        )];
    }

    /**
     * @param  array<string, int>|null  $usage
     */
    private function usage(?array $usage): ?TokenUsage
    {
        if ($usage === null) {
            return null;
        }

        return new TokenUsage(
            inputTextTokens: $usage['input_text_tokens'],
            inputImageTokens: $usage['input_image_tokens'],
            outputTokens: $usage['output_tokens'],
            reasoningTokens: $usage['reasoning_tokens'],
        );
    }

    private function date(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }
}
