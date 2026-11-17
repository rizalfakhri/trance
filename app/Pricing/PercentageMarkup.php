<?php

namespace App\Pricing;

use App\Contracts\Billing\PlatformFee;
use App\Data\GenerationCharge;
use App\Data\GenerationCost;

final readonly class PercentageMarkup implements PlatformFee
{
    private const BASIS_POINTS_PER_WHOLE = 10_000;

    /**
     * @param  array<string, int>  $providerBasisPoints
     */
    public function __construct(
        private int $defaultBasisPoints,
        private array $providerBasisPoints = [],
    ) {}

    public function apply(GenerationCost $cost, string $provider): GenerationCharge
    {
        $basisPoints = $this->providerBasisPoints[$provider] ?? $this->defaultBasisPoints;
        $fee = intdiv($cost->total() * $basisPoints + self::BASIS_POINTS_PER_WHOLE - 1, self::BASIS_POINTS_PER_WHOLE);

        return new GenerationCharge($cost, $basisPoints, $fee);
    }
}
