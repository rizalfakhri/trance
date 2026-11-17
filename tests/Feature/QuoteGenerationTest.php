<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'generation.platform_fee' => ['default_basis_points' => 2500, 'providers' => []],
    ]);

    Http::fake(['https://pixelforge.test/v1/pricing' => Http::response([
        'currency' => 'usd',
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => ['high' => ['1536x1024' => 6450], 'medium' => ['1024x1024' => 1100]],
            'reasoning' => ['high' => ['min' => 1000, 'max' => 8000], 'medium' => ['min' => 200, 'max' => 2000]],
        ],
    ])]);

    $this->user = User::factory()->create();
});

test('guests cannot request quotes', function () {
    $this->postJson(route('generations.quote'), ['provider' => 'pixelforge'])->assertUnauthorized();

    Http::assertNothingSent();
});

test('a quote returns the worst case price including the platform fee', function () {
    $this->actingAs($this->user)->postJson(route('generations.quote'), [
        'provider' => 'pixelforge',
        'prompt' => str_repeat('a', 400),
        'options' => ['size' => '1536x1024', 'quality' => 'high'],
        'reference_types' => ['image/png', 'image/jpeg'],
    ])->assertOk()->assertExactJson([
        'currency' => 'usd',
        'provider_cost' => 373_875_000,
        'fee' => 93_468_750,
        'fee_basis_points' => 2500,
        'total' => 467_343_750,
    ]);
});

test('omitted options fall back to the provider defaults', function () {
    $this->actingAs($this->user)->postJson(route('generations.quote'), ['provider' => 'pixelforge'])
        ->assertOk()
        ->assertJsonPath('provider_cost', 69_000_000);
});

test('quotes are validated against the provider capabilities', function (array $payload, string $field) {
    $this->actingAs($this->user)->postJson(route('generations.quote'), [...['provider' => 'pixelforge'], ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown provider' => [['provider' => 'missing'], 'provider'],
    'unknown model' => [['model' => 'missing'], 'model'],
    'unsupported option value' => [['options' => ['quality' => 'ultra']], 'options.quality'],
    'prompt too long' => [['prompt' => str_repeat('a', 4001)], 'prompt'],
    'too many references' => [['reference_types' => array_fill(0, 5, 'image/png')], 'reference_types'],
    'unsupported reference type' => [['reference_types' => ['image/gif']], 'reference_types.0'],
]);

test('quotes are empty when the provider pricing is unavailable', function () {
    config(['services.pixelforge.base_url' => 'https://pixelforge-down.test/v1']);
    Http::fake(['https://pixelforge-down.test/v1/pricing' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'Busy']], 503)]);

    $this->actingAs($this->user)->postJson(route('generations.quote'), ['provider' => 'pixelforge'])
        ->assertOk()
        ->assertExactJson(['total' => null]);
});

test('disabled providers cannot be quoted', function () {
    config(['generation.disabled_providers' => ['pixelforge']]);

    $this->actingAs($this->user)->postJson(route('generations.quote'), ['provider' => 'pixelforge'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');
});

test('reference urls are included in the quote', function () {
    $this->actingAs($this->user)->postJson(route('generations.quote'), [
        'provider' => 'pixelforge',
        'reference_urls' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ])->assertOk()->assertJsonPath('provider_cost', 69_000_000 + 2 * 7_650_000);
});
