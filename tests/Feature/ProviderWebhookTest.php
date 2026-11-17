<?php

use App\Events\ProviderWebhookReceived;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Providers\FakeImageProvider;
use Tests\Fixtures\Providers\FakeVideoProvider;

beforeEach(function () {
    config(['generation.providers' => [FakeImageProvider::class, FakeVideoProvider::class]]);
    Event::fake([ProviderWebhookReceived::class]);
});

test('webhooks for unknown providers return 404', function () {
    $this->postJson(route('webhooks.handle', 'missing'))->assertNotFound();

    Event::assertNotDispatched(ProviderWebhookReceived::class);
});

test('webhooks for providers that do not receive them return 404', function () {
    $this->postJson(route('webhooks.handle', 'fake-video'))->assertNotFound();
});

test('webhooks with an invalid signature return 401', function () {
    $this->postJson(route('webhooks.handle', 'fake-image'), ['id' => 'evt_1'], ['X-Fake-Signature' => 'forged'])
        ->assertUnauthorized();

    Event::assertNotDispatched(ProviderWebhookReceived::class);
});

test('verified webhooks are handed to the provider and dispatched as an event', function () {
    $this->postJson(
        route('webhooks.handle', 'fake-image'),
        ['id' => 'evt_1', 'type' => 'generation.processing', 'generation_id' => 'gen_42'],
        ['X-Fake-Signature' => FakeImageProvider::VALID_SIGNATURE],
    )->assertOk()->assertJson(['received' => true]);

    Event::assertDispatched(ProviderWebhookReceived::class, fn (ProviderWebhookReceived $received): bool => $received->provider === 'fake-image'
        && $received->event->id === 'evt_1'
        && $received->event->type === 'generation.processing'
        && $received->event->generation?->externalId === 'gen_42');
});
