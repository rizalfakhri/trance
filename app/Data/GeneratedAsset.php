<?php

namespace App\Data;

use App\Enums\Modality;
use Carbon\CarbonImmutable;

final readonly class GeneratedAsset
{
    public function __construct(
        public Modality $modality,
        public string $url,
        public string $mimeType,
        public ?int $width = null,
        public ?int $height = null,
        public ?float $durationSeconds = null,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
