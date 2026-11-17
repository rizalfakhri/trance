<?php

namespace App\Http\Controllers;

use App\Events\ProviderWebhookReceived;
use App\Exceptions\Providers\InvalidWebhookSignatureException;
use App\Exceptions\Providers\UnknownProviderException;
use App\Exceptions\Providers\UnsupportedCapabilityException;
use App\Registries\ProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderWebhookController extends Controller
{
    public function __invoke(Request $request, ProviderRegistry $registry, string $provider): JsonResponse
    {
        try {
            $receiver = $registry->webhooks($provider);
        } catch (UnknownProviderException|UnsupportedCapabilityException) {
            abort(404);
        }

        try {
            $event = $receiver->handleWebhook($request);
        } catch (InvalidWebhookSignatureException) {
            abort(401);
        }

        ProviderWebhookReceived::dispatch($provider, $event);

        return response()->json(['received' => true]);
    }
}
