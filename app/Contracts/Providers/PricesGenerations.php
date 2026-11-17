<?php

namespace App\Contracts\Providers;

use App\Data\GenerationRequest;

interface PricesGenerations
{
    public function pricing(GenerationRequest $request): PricingCatalog;
}
