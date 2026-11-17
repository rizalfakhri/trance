<?php

namespace App\Contracts\Providers;

use App\Data\GenerationRequest;

interface EstimatesUsage
{
    /**
     * The most a request can consume, so quotes and balance holds never undershoot the final charge.
     */
    public function estimateUsage(GenerationRequest $request): Usage;
}
