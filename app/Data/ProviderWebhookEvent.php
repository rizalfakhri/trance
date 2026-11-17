<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderWebhookEvent
{
    public function __construct(
        public string $id,
        public string $type,
        public CarbonImmutable $occurredAt,
        public ?ProviderGeneration $generation = null,
    ) {}
}
