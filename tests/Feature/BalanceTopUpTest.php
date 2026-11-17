<?php

use App\Billing\Transactions;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['generation.top_up_amounts' => [5, 10, 25, 50]]);
    $this->user = User::factory()->create();
});

test('guests cannot top up', function () {
    $this->post(route('balance.top-ups.store'), ['amount' => 10, 'idempotency_key' => (string) Str::uuid()])
        ->assertRedirect(route('login'));
});

test('a top up credits the chosen amount and returns to the previous page', function () {
    $this->actingAs($this->user)
        ->from(route('home'))
        ->post(route('balance.top-ups.store'), ['amount' => 10, 'idempotency_key' => (string) Str::uuid()])
        ->assertRedirect(route('home'));

    expect(app(Transactions::class)->summary($this->user)->topUps)->toBe(10_000_000_000);
});

test('repeating a top up with the same idempotency key credits it once', function () {
    $payload = ['amount' => 25, 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs($this->user)->post(route('balance.top-ups.store'), $payload);
    $this->actingAs($this->user)->post(route('balance.top-ups.store'), $payload);

    expect(app(Transactions::class)->balance($this->user))->toBe(25_000_000_000);
});

test('the same idempotency key from different users credits both', function () {
    $key = (string) Str::uuid();
    $otherUser = User::factory()->create();

    $this->actingAs($this->user)->post(route('balance.top-ups.store'), ['amount' => 5, 'idempotency_key' => $key]);
    $this->actingAs($otherUser)->post(route('balance.top-ups.store'), ['amount' => 5, 'idempotency_key' => $key]);

    expect(app(Transactions::class)->balance($otherUser))->toBe(5_000_000_000);
});

test('top ups are limited to the offered amounts and need an idempotency key', function (array $payload, string $field) {
    $this->actingAs($this->user)
        ->post(route('balance.top-ups.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(app(Transactions::class)->balance($this->user))->toBe(0);
})->with([
    'unoffered amount' => [['amount' => 7, 'idempotency_key' => '9b2f6a52-7f0b-4f4e-9d0c-5d3c9c1f2a10'], 'amount'],
    'missing key' => [['amount' => 10], 'idempotency_key'],
    'malformed key' => [['amount' => 10, 'idempotency_key' => 'not-a-uuid'], 'idempotency_key'],
]);
