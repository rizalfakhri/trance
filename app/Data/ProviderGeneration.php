<?php

namespace App\Data;

use App\Contracts\Providers\PricingCatalog;
use App\Contracts\Providers\Usage;
use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

final readonly class ProviderGeneration
{
    /**
     * @param  list<GeneratedAsset>  $outputs
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $externalId,
        public GenerationStatus $status,
        public array $outputs = [],
        public ?Usage $usage = null,
        public ?PricingCatalog $pricing = null,
        public ?ProviderError $error = null,
        public ?CarbonImmutable $startedAt = null,
        public ?CarbonImmutable $completedAt = null,
        public array $metadata = [],
    ) {}

    public function generationId(): ?string
    {
        return $this->metadata[GenerationRequest::GENERATION_ID_METADATA_KEY] ?? null;
    }

    public function attempt(): ?int
    {
        $attempt = $this->metadata[GenerationRequest::ATTEMPT_METADATA_KEY] ?? null;

        return $attempt === null ? null : (int) $attempt;
    }
}
