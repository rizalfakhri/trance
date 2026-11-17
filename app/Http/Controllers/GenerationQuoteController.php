<?php

namespace App\Http\Controllers;

use App\Exceptions\Providers\ProviderException;
use App\Http\Requests\QuoteGenerationRequest;
use App\Pricing\GenerationQuoter;
use Illuminate\Http\JsonResponse;

class GenerationQuoteController extends Controller
{
    public function __invoke(QuoteGenerationRequest $request, GenerationQuoter $quoter): JsonResponse
    {
        try {
            $charge = $quoter->quote($request->validated('provider'), $request->toGenerationRequest());
        } catch (ProviderException) {
            return response()->json(['total' => null]);
        }

        return response()->json([
            'currency' => $charge->currency(),
            'provider_cost' => $charge->cost->total(),
            'fee' => $charge->fee,
            'fee_basis_points' => $charge->feeBasisPoints,
            'total' => $charge->total(),
        ]);
    }
}
