<?php

use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests are sent to log in', function () {
    $this->get(route('gallery'))->assertRedirect(route('login'));
});

test('the gallery is empty for users without generations', function () {
    Generation::factory()->create();

    $this->actingAs($this->user)
        ->get(route('gallery'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Gallery')
            ->where('generations', [])
            ->where('nextPage', null));
});

test('the gallery lists only the user generations, newest first', function () {
    $older = Generation::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
    $newer = Generation::factory()->for($this->user)->failed()->create();
    Generation::factory()->create();

    $this->actingAs($this->user)
        ->get(route('gallery'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 2)
            ->where('generations.0.id', $newer->id)
            ->where('generations.0.status', 'failed')
            ->where('generations.0.url', route('generations.show', $newer))
            ->where('generations.1.id', $older->id));
});

test('the gallery loads further pages as the user scrolls', function () {
    Generation::factory()->for($this->user)->count(25)->create();

    $this->actingAs($this->user)
        ->get(route('gallery'))
        ->assertInertia(fn (Assert $page) => $page->has('generations', 24)->where('nextPage', 2));

    $this->actingAs($this->user)
        ->get(route('gallery', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('generations', 1)->where('nextPage', null));
});
