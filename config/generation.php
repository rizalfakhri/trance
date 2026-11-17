<?php

use App\AI\Providers\OpenAI\OpenAIProvider;
use App\AI\Providers\PixelForge\PixelForgeProvider;

return [

    'default_provider' => env('GENERATION_PROVIDER', 'pixelforge'),

    'disabled_providers' => array_values(array_filter(explode(',', (string) env('GENERATION_DISABLED_PROVIDERS', '')))),

    'providers' => [
        PixelForgeProvider::class,
        OpenAIProvider::class,
    ],

    'storage' => [
        'disk' => env('GENERATION_DISK', 'local'),
        'max_output_bytes' => 25 * 1024 * 1024,
        'reference_url_ttl' => 60,
    ],

    'top_up_amounts' => [5, 10, 25, 50],

    'platform_fee' => [
        'default_basis_points' => (int) env('GENERATION_PLATFORM_FEE_BASIS_POINTS', 2500),
        'providers' => [],
    ],

];
