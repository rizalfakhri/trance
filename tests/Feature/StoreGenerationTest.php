<?php

use App\Billing\Transactions;
use App\Enums\AssetRole;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    usePixelForgeTestConfig();
    Http::fake([PIXELFORGE_TEST_URL.'/pricing' => Http::response(pixelForgePricingResponse())]);
    Queue::fake();
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->transactions = app(Transactions::class);
});

function generationPayload(array $overrides = []): array
{
    return [
        'provider' => 'pixelforge',
        'prompt' => str_repeat('a', 400),
        'options' => ['size' => '1536x1024', 'quality' => 'high'],
        ...$overrides,
    ];
}

test('guests cannot start generations', function () {
    $this->postJson(route('generations.store'), generationPayload())->assertUnauthorized();
});

test('a generation is created with a balance hold, its references and a queued submission', function () {
    $this->transactions->topUp($this->user, 10_000_000_000);

    $response = $this->actingAs($this->user)->post(route('generations.store'), generationPayload([
        'references' => [UploadedFile::fake()->image('style.png', 16, 16)],
    ]), ['Accept' => 'application/json']);

    $generation = Generation::query()->sole();

    $response->assertCreated()->assertExactJson([
        'id' => $generation->id,
        'url' => route('generations.show', $generation),
    ]);

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->options)->toBe(['size' => '1536x1024', 'quality' => 'high'])
        ->and($generation->quote_total)->toBe(457_781_250)
        ->and($generation->fee_basis_points)->toBe(2500)
        ->and($generation->hold->amount)->toBe(-457_781_250)
        ->and($generation->assets()->where('role', AssetRole::Reference)->count())->toBe(1)
        ->and($generation->events()->pluck('message')->all())->toBe(['Request received'])
        ->and($this->transactions->balance($this->user))->toBe(10_000_000_000 - 457_781_250);

    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($generation));
});

test('generations beyond the balance are refused without creating anything', function () {
    $this->transactions->topUp($this->user, 100_000_000);

    $this->actingAs($this->user)->postJson(route('generations.store'), generationPayload())
        ->assertStatus(402)
        ->assertJson([
            'message' => "You don't have enough balance to generate this image. Top up your credits to continue.",
            'required' => 448_218_750,
            'available' => 100_000_000,
        ]);

    expect(Generation::query()->exists())->toBeFalse()
        ->and($this->transactions->balance($this->user))->toBe(100_000_000);
    Queue::assertNothingPushed();
});

test('generation requests are validated against the provider capabilities', function (array $overrides, string $field) {
    $this->transactions->topUp($this->user, 10_000_000_000);

    $this->actingAs($this->user)->postJson(route('generations.store'), generationPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing prompt' => [['prompt' => ''], 'prompt'],
    'unsupported option' => [['options' => ['quality' => 'ultra']], 'options.quality'],
    'too many references' => [['references' => array_fill(0, 5, UploadedFile::fake()->image('a.png'))], 'references'],
    'unsupported reference type' => [['references' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]], 'references.0'],
]);

test('reference image urls are saved with the generation and priced like uploads', function () {
    $this->transactions->topUp($this->user, 10_000_000_000);

    $this->actingAs($this->user)->postJson(route('generations.store'), generationPayload([
        'reference_urls' => ['https://example.com/style.png'],
    ]))->assertCreated();

    $generation = Generation::query()->sole();

    expect($generation->reference_urls)->toBe(['https://example.com/style.png'])
        ->and($generation->quote_total)->toBe(457_781_250);
});

test('uploads and urls share the reference image limit', function () {
    $this->transactions->topUp($this->user, 10_000_000_000);

    $this->actingAs($this->user)->post(route('generations.store'), generationPayload([
        'references' => array_fill(0, 3, UploadedFile::fake()->image('a.png')),
        'reference_urls' => ['https://example.com/1.png', 'https://example.com/2.png'],
    ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['references' => 'You can add up to 4 reference images.']);

    expect(Generation::query()->exists())->toBeFalse();
});

test('reference urls must be http or https', function (string $url) {
    $this->transactions->topUp($this->user, 10_000_000_000);

    $this->actingAs($this->user)->postJson(route('generations.store'), generationPayload(['reference_urls' => [$url]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reference_urls.0');
})->with(['ftp://example.com/a.png', 'not a url', 'javascript:alert(1)']);
