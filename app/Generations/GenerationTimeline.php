<?php

namespace App\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\GenerationEvent;

final class GenerationTimeline
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        Generation $generation,
        string $type,
        ?string $message,
        ?GenerationStatus $status = null,
        ?string $providerEventId = null,
        ?array $payload = null,
    ): GenerationEvent {
        return GenerationEvent::query()->create([
            'generation_id' => $generation->getKey(),
            'provider_event_id' => $providerEventId,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
