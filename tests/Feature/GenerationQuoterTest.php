<?php

use App\Data\GenerationRequest;
use App\Data\ReferenceAsset;
use App\Enums\Modality;
use App\Exceptions\Providers\UnsupportedCapabilityException;
use App\Pricing\GenerationQuoter;
use Tests\Fixtures\Providers\FakeImageProvider;
use Tests\Fixtures\Providers\FakeVideoProvider;

beforeEach(function () {
    config([
        'generation.providers' => [FakeImageProvider::class, FakeVideoProvider::class],
        'generation.platform_fee' => ['default_basis_points' => 2500, 'providers' => []],
    ]);
});

function quoteRequest(): GenerationRequest
{
    return new GenerationRequest(
        prompt: str_repeat('a', 400),
        modality: Modality::Image,
        idempotencyKey: 'quote-test',
        references: [
            new ReferenceAsset('https://example.test/one.png', 'image/png'),
            new ReferenceAsset('https://example.test/two.png', 'image/png'),
        ],
    );
}

test('a quote prices the estimated worst case usage with the platform fee', function () {
    $charge = app(GenerationQuoter::class)->quote('fake-image', quoteRequest());

    expect($charge->cost->total())->toBe(373_800_000)
        ->and($charge->fee)->toBe(93_450_000)
        ->and($charge->total())->toBe(467_250_000);
});

test('providers that cannot estimate usage cannot be quoted', function () {
    app(GenerationQuoter::class)->quote('fake-video', quoteRequest());
})->throws(UnsupportedCapabilityException::class);
