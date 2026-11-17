<?php

use App\Enums\Modality;
use App\Exceptions\Providers\UnknownProviderException;
use App\Exceptions\Providers\UnsupportedCapabilityException;
use App\Registries\ProviderRegistry;
use Tests\Fixtures\Providers\FakeImageProvider;
use Tests\Fixtures\Providers\FakeVideoProvider;

beforeEach(function () {
    config([
        'generation.default_provider' => 'fake-image',
        'generation.providers' => [FakeImageProvider::class, FakeVideoProvider::class],
    ]);
});

test('the registry is built from the configured provider classes', function () {
    $registry = app(ProviderRegistry::class);

    expect(array_keys($registry->all()))->toBe(['fake-image', 'fake-video'])
        ->and($registry->get('fake-video'))->toBeInstanceOf(FakeVideoProvider::class);
});

test('the default provider follows configuration', function () {
    expect(app(ProviderRegistry::class)->default()->name())->toBe('fake-image');
});

test('registering a provider name twice is rejected', function () {
    app(ProviderRegistry::class)->register(new FakeImageProvider);
})->throws(InvalidArgumentException::class);

test('unknown providers are rejected', function () {
    app(ProviderRegistry::class)->get('missing');
})->throws(UnknownProviderException::class);

test('capabilities a provider does not implement are rejected', function () {
    app(ProviderRegistry::class)->generator('fake-video');
})->throws(UnsupportedCapabilityException::class);

test('capability accessors return providers that implement them', function () {
    $registry = app(ProviderRegistry::class);

    expect($registry->generator('fake-image'))->toBeInstanceOf(FakeImageProvider::class)
        ->and($registry->webhooks('fake-image'))->toBeInstanceOf(FakeImageProvider::class);
});

test('capabilities can be filtered by modality', function () {
    $providers = array_map(fn ($capabilities) => $capabilities->provider(), app(ProviderRegistry::class)->capabilities(Modality::Video));

    expect($providers)->toBe(['fake-video']);
});

test('disabled providers stay registered but are not enabled', function () {
    config(['generation.disabled_providers' => ['fake-video']]);
    $registry = app(ProviderRegistry::class);

    expect($registry->has('fake-video'))->toBeTrue()
        ->and($registry->isEnabled('fake-video'))->toBeFalse()
        ->and(array_keys($registry->enabled()))->toBe(['fake-image']);
});

test('a disabled default provider falls back to the first enabled provider', function () {
    config(['generation.disabled_providers' => ['fake-image']]);

    expect(app(ProviderRegistry::class)->default()->name())->toBe('fake-video');
});
