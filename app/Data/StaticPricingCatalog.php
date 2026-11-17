<?php

namespace App\Data;

use App\Contracts\Providers\PricingCatalog;

final readonly class StaticPricingCatalog implements PricingCatalog
{
    /**
     * @param  array<string, UnitRate>  $additionalRates
     */
    public function __construct(
        public UnitRate $inputTextTokenRate,
        public UnitRate $inputImageTokenRate,
        public UnitRate $outputTokenRate,
        public UnitRate $reasoningTokenRate,
        public array $additionalRates = [],
        public string $currency = 'usd',
    ) {}

    public function currency(): string
    {
        return $this->currency;
    }

    public function inputTextTokenRate(): UnitRate
    {
        return $this->inputTextTokenRate;
    }

    public function inputImageTokenRate(): UnitRate
    {
        return $this->inputImageTokenRate;
    }

    public function outputTokenRate(): UnitRate
    {
        return $this->outputTokenRate;
    }

    public function reasoningTokenRate(): UnitRate
    {
        return $this->reasoningTokenRate;
    }

    public function additionalRates(): array
    {
        return $this->additionalRates;
    }
}
