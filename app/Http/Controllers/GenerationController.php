<?php

namespace App\Http\Controllers;

use App\Exceptions\Billing\InsufficientBalanceException;
use App\Exceptions\Providers\ProviderException;
use App\Generations\StartGeneration;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function store(StoreGenerationRequest $request, StartGeneration $startGeneration): JsonResponse
    {
        try {
            $generation = $startGeneration->handle(
                $request->user(),
                $request->validated('provider'),
                $request->toGenerationRequest(),
                $request->referenceFiles(),
                $request->referenceUrls(),
            );
        } catch (InsufficientBalanceException $exception) {
            return response()->json([
                'message' => "You don't have enough balance to generate this image. Top up your credits to continue.",
                'required' => $exception->required,
                'available' => $exception->available,
            ], 402);
        } catch (ProviderException) {
            return response()->json(['message' => 'The provider is unavailable. Please try again.'], 503);
        }

        return response()->json([
            'id' => $generation->id,
            'url' => route('generations.show', $generation),
        ], 201);
    }

    public function show(Request $request, Generation $generation): Response
    {
        abort_unless($generation->user()->is($request->user()), 404);

        $generation->load(['assets', 'events']);

        return Inertia::render('Generation', [
            'generation' => (new GenerationResource($generation))->resolve($request),
        ]);
    }
}
