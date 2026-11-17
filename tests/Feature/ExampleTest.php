<?php

use Inertia\Testing\AssertableInertia as Assert;

test('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('home renders the home page component', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->component('Home'));
});

test('home shares the image providers and the default provider', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaultProvider', 'pixelforge')
            ->where('providers.0.provider', 'pixelforge')
            ->where('providers.0.models.0.options.1.key', 'quality'));
});

test('disabled providers are listed as unavailable and not chosen as default', function () {
    config(['generation.disabled_providers' => ['pixelforge']]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('providers.0.provider', 'pixelforge')
            ->where('providers.0.enabled', false)
            ->where('providers.1.enabled', true)
            ->where('defaultProvider', 'openai'));
});
