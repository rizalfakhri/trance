<?php

namespace App\Contracts\Billing;

use App\Data\GenerationCharge;
use App\Data\GenerationCost;

interface PlatformFee
{
    public function apply(GenerationCost $cost, string $provider): GenerationCharge;
}
