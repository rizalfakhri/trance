<?php

namespace App\Contracts\Providers;

use App\Data\UnitRate;

interface PricingCatalog
{
    public function currency(): string;

    public function inputTextTokenRate(): UnitRate;

    public function inputImageTokenRate(): UnitRate;

    public function outputTokenRate(): UnitRate;

    public function reasoningTokenRate(): UnitRate;

    /**
     * @return array<string, UnitRate>
     */
    public function additionalRates(): array;
}
