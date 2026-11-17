<?php

namespace App\Pricing;

use App\Contracts\Billing\PlatformFee;
use App\Data\GenerationCharge;
use App\Data\GenerationCost;
use App\Data\GenerationRequest;
use App\Exceptions\Providers\UnknownProviderException;
use App\Exceptions\Providers\UnsupportedCapabilityException;
use App\Registries\ProviderRegistry;

final readonly class GenerationQuoter
{
    public function __construct(
        private ProviderRegistry $registry,
        private PlatformFee $platformFee,
    ) {}

    /**
     * @throws UnknownProviderException
     * @throws UnsupportedCapabilityException
     */
    public function quote(string $provider, GenerationRequest $request): GenerationCharge
    {
        $usage = $this->registry->estimator($provider)->estimateUsage($request);
        $pricing = $this->registry->pricing($provider)->pricing($request);

        return $this->platformFee->apply(GenerationCost::calculate($usage, $pricing), $provider);
    }
}
