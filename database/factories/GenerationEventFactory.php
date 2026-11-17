<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\GenerationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationEvent>
 */
class GenerationEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generation_id' => Generation::factory(),
            'provider_event_id' => 'evt_'.fake()->unique()->bothify('????????'),
            'type' => 'generation.processing',
            'status' => GenerationStatus::Processing,
            'message' => 'Worker attached',
            'occurred_at' => now(),
        ];
    }
}
