<?php

namespace App\Data;

final readonly class ProviderGenerationPage
{
    /**
     * @param  list<ProviderGeneration>  $generations
     */
    public function __construct(
        public array $generations,
        public ?string $nextCursor = null,
    ) {}
}
