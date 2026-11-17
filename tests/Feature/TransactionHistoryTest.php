<?php

use App\Billing\Transactions;
use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->transactions = app(Transactions::class);
});

test('guests are sent to log in', function () {
    $this->get(route('transactions'))->assertRedirect(route('login'));
});

test('the history lists the user transactions newest first with their generation', function () {
    $generation = Generation::factory()->for($this->user)->create();
    $this->transactions->topUp($this->user, 10_000_000_000, 'Top-up');
    $this->travel(1)->minutes();
    $this->transactions->settle($this->transactions->hold($this->user, 500_000_000, $generation), 400_000_000);
    $this->transactions->topUp(User::factory()->create(), 99_000_000_000);

    $this->actingAs($this->user)
        ->get(route('transactions'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transactions')
            ->has('transactions', 4)
            ->where('transactions.0.label', 'Generation')
            ->where('transactions.0.amount', -400_000_000)
            ->where('transactions.0.generation.url', route('generations.show', $generation))
            ->where('transactions.3.label', 'Top-up')
            ->where('summary.available', 9_600_000_000)
            ->where('summary.spent', 400_000_000)
            ->where('summary.held', 0));
});

test('open holds are marked pending until they are resolved', function () {
    $this->transactions->topUp($this->user, 10_000_000_000);
    $this->transactions->hold($this->user, 500_000_000);

    $this->actingAs($this->user)
        ->get(route('transactions'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.0.label', 'Reserved')
            ->where('transactions.0.isPending', true)
            ->where('summary.held', 500_000_000));
});

test('the history loads further pages as the user scrolls', function () {
    foreach (range(1, 51) as $index) {
        $this->transactions->topUp($this->user, 1_000_000);
    }

    $this->actingAs($this->user)
        ->get(route('transactions'))
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 50)->where('nextPage', 2));

    $this->actingAs($this->user)
        ->get(route('transactions', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1)->where('nextPage', null));
});
