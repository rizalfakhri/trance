<?php

use App\Models\Generation;
use App\Models\GenerationEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owners see their generation with its options, cost and messages', function () {
    $generation = Generation::factory()->create(['options' => ['size' => '1536x1024', 'quality' => 'high'], 'quote_total' => 467_343_750]);
    GenerationEvent::factory()->for($generation)->create(['message' => 'Request received', 'status' => 'queued']);

    $this->actingAs($generation->user)
        ->get(route('generations.show', $generation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Generation')
            ->where('generation.id', $generation->id)
            ->where('generation.status', 'queued')
            ->where('generation.provider.label', 'PixelForge')
            ->where('generation.options.0', ['key' => 'size', 'label' => 'Size', 'value' => '1536×1024'])
            ->where('generation.expectedSize', ['width' => 1536, 'height' => 1024])
            ->where('generation.cost', ['estimate' => 467_343_750, 'actual' => null, 'currency' => 'usd'])
            ->where('generation.events.0.message', 'Request received'));
});

test('other users cannot see a generation', function () {
    $generation = Generation::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('generations.show', $generation))
        ->assertNotFound();
});

test('guests are sent to log in', function () {
    $this->get(route('generations.show', Generation::factory()->create()))->assertRedirect(route('login'));
});

test('the generation page returns 404 for an id that is not a uuid', function () {
    $this->actingAs(User::factory()->create())->get('/g/not-a-uuid')->assertNotFound();
});

test('the selected model is shown with its label', function () {
    $generation = Generation::factory()->create(['provider' => 'openai', 'model' => 'gpt-image-2.6-flare']);

    $this->actingAs($generation->user)
        ->get(route('generations.show', $generation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generation.provider.label', 'OpenAI')
            ->where('generation.provider.logoUrl', asset('images/providers/openai.svg'))
            ->where('generation.provider.darkLogoUrl', asset('images/providers/openai-dark.svg'))
            ->where('generation.model', ['id' => 'gpt-image-2.6-flare', 'label' => 'GPT Image 2.6 Flare']));
});

test('generations without a model show none', function () {
    $generation = Generation::factory()->create(['model' => null]);

    $this->actingAs($generation->user)
        ->get(route('generations.show', $generation))
        ->assertInertia(fn (Assert $page) => $page->where('generation.model', null));
});

test('finished generations show both the estimate and the actual cost', function () {
    $generation = Generation::factory()->succeeded()->create(['quote_total' => 467_343_750, 'charge_total' => 403_900_000]);

    $this->actingAs($generation->user)
        ->get(route('generations.show', $generation))
        ->assertInertia(fn (Assert $page) => $page->where('generation.cost', ['estimate' => 467_343_750, 'actual' => 403_900_000, 'currency' => 'usd']));
});

test('failed generations keep their estimate and cost nothing', function () {
    $generation = Generation::factory()->failed()->create(['quote_total' => 467_343_750]);

    $this->actingAs($generation->user)
        ->get(route('generations.show', $generation))
        ->assertInertia(fn (Assert $page) => $page->where('generation.cost', ['estimate' => 467_343_750, 'actual' => 0, 'currency' => 'usd']));
});

test('reference urls are shown alongside the generation', function () {
    $generation = Generation::factory()->create(['reference_urls' => ['https://example.com/style.png']]);

    $this->actingAs($generation->user)
        ->get(route('generations.show', $generation))
        ->assertInertia(fn (Assert $page) => $page->where('generation.references', [['id' => 'url-0', 'url' => 'https://example.com/style.png']]));
});
