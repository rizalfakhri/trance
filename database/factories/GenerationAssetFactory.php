<?php

namespace Database\Factories;

use App\Enums\AssetRole;
use App\Models\Generation;
use App\Models\GenerationAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationAsset>
 */
class GenerationAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generation_id' => Generation::factory(),
            'role' => AssetRole::Output,
            'position' => 0,
            'disk' => 'local',
            'path' => 'generations/'.fake()->uuid().'/outputs/0.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'width' => 1024,
            'height' => 1024,
        ];
    }

    public function reference(): static
    {
        return $this->state(fn (): array => ['role' => AssetRole::Reference]);
    }

    public function output(): static
    {
        return $this->state(fn (): array => ['role' => AssetRole::Output]);
    }
}
