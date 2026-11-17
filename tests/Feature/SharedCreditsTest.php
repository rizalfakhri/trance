<?php

use App\Billing\Transactions;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated pages share the user available credits', function () {
    $user = User::factory()->create();
    $transactions = app(Transactions::class);
    $transactions->topUp($user, 12_500_000_000);
    $transactions->hold($user, 2_000_000_000);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.credits.available', 10_500_000_000)
            ->where('auth.credits.currency', 'usd')
            ->where('auth.credits.topUpAmounts', config('generation.top_up_amounts')));
});

test('guests are not shared any credits', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.credits', null));
});
