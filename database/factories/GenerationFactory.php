<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\Modality;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'pixelforge',
            'modality' => Modality::Image,
            'prompt' => fake()->sentence(),
            'options' => ['size' => '1024x1024', 'quality' => 'medium'],
            'status' => GenerationStatus::Queued,
            'submitted_at' => now(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => GenerationStatus::Succeeded,
            'external_id' => 'gen_'.fake()->unique()->bothify('????????'),
            'started_at' => now()->subSeconds(20),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => GenerationStatus::Failed,
            'external_id' => 'gen_'.fake()->unique()->bothify('????????'),
            'error_code' => 'generation_failed',
            'error_message' => 'The model could not produce an image.',
            'completed_at' => now(),
        ]);
    }
}
