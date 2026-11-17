<?php

namespace App\Data;

use App\Enums\Modality;

final readonly class GenerationRequest
{
    public const GENERATION_ID_METADATA_KEY = 'trance_generation_id';

    public const ATTEMPT_METADATA_KEY = 'trance_attempt';

    /**
     * @param  array<string, string|int|bool>  $options
     * @param  list<ReferenceAsset>  $references
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $prompt,
        public Modality $modality,
        public ?string $model = null,
        public array $options = [],
        public array $references = [],
        public array $metadata = [],
        public ?string $idempotencyKey = null,
    ) {}

    public function generationId(): ?string
    {
        return $this->metadata[self::GENERATION_ID_METADATA_KEY] ?? null;
    }
}
