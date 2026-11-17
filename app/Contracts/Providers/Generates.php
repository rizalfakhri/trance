<?php

namespace App\Contracts\Providers;

use App\Data\GenerationRequest;
use App\Data\ProviderGeneration;
use App\Data\ProviderGenerationPage;
use App\Exceptions\Providers\GenerationNotFoundException;
use App\Exceptions\Providers\ProviderException;

/**
 * Providers must send the request's idempotency key and metadata to the API, and return the
 * metadata the API echoes back on every ProviderGeneration, so generations can be linked to
 * local records before the provider's own id is known.
 */
interface Generates
{
    /**
     * @throws ProviderException
     */
    public function generate(GenerationRequest $request): ProviderGeneration;

    /**
     * @throws GenerationNotFoundException
     * @throws ProviderException
     */
    public function generation(string $externalId): ProviderGeneration;

    /**
     * @throws ProviderException
     */
    public function generations(int $limit = 20, ?string $cursor = null): ProviderGenerationPage;
}
