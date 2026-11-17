<?php

use App\Billing\Transactions;
use App\Enums\TransactionType;
use App\Exceptions\Billing\InsufficientBalanceException;
use App\Models\User;

beforeEach(function () {
    $this->transactions = app(Transactions::class);
    $this->user = User::factory()->create();
});

test('a new user has an empty balance', function () {
    expect($this->transactions->balance($this->user))->toBe(0);
});

test('top ups and credits increase the balance', function () {
    $this->transactions->topUp($this->user, 10_000, 'Card top-up');
    $this->transactions->credit($this->user, 2_500, 'Welcome credit');

    expect($this->transactions->balance($this->user))->toBe(12_500);
});

test('balances are isolated per user', function () {
    $this->transactions->topUp($this->user, 10_000);
    $this->transactions->topUp(User::factory()->create(), 99_000);

    expect($this->transactions->balance($this->user))->toBe(10_000);
});

test('non positive amounts are rejected', function (int $amount) {
    $this->transactions->topUp($this->user, $amount);
})->throws(InvalidArgumentException::class)->with([0, -100]);

test('a top up with a reused idempotency key is recorded once', function () {
    $first = $this->transactions->topUp($this->user, 10_000, idempotencyKey: 'payment_1');
    $second = $this->transactions->topUp($this->user, 10_000, idempotencyKey: 'payment_1');

    expect($second->is($first))->toBeTrue()
        ->and($this->transactions->balance($this->user))->toBe(10_000);
});

test('a hold reserves part of the balance', function () {
    $this->transactions->topUp($this->user, 10_000);

    $hold = $this->transactions->hold($this->user, 4_000);

    expect($hold->type)->toBe(TransactionType::Hold)
        ->and($this->transactions->balance($this->user))->toBe(6_000);
});

test('a hold larger than the balance is refused and not recorded', function () {
    $this->transactions->topUp($this->user, 1_000);

    try {
        $this->transactions->hold($this->user, 4_000);
        $this->fail('Expected the hold to be refused.');
    } catch (InsufficientBalanceException $exception) {
        expect($exception->required)->toBe(4_000)
            ->and($exception->available)->toBe(1_000);
    }

    expect($this->user->transactions()->where('type', TransactionType::Hold)->exists())->toBeFalse();
});

test('releasing a hold restores the balance', function () {
    $this->transactions->topUp($this->user, 10_000);
    $hold = $this->transactions->hold($this->user, 4_000);

    $this->transactions->release($hold);

    expect($this->transactions->balance($this->user))->toBe(10_000);
});

test('settling a hold charges the actual usage and frees the rest', function () {
    $this->transactions->topUp($this->user, 10_000);
    $hold = $this->transactions->hold($this->user, 4_000);

    $usage = $this->transactions->settle($hold, 2_500, 'Generation');

    expect($usage->type)->toBe(TransactionType::Usage)
        ->and($usage->amount)->toBe(-2_500)
        ->and($usage->hold_id)->toBe($hold->id)
        ->and($this->transactions->balance($this->user))->toBe(7_500);
});

test('a hold can only be resolved once', function (string $firstResolution) {
    $this->transactions->topUp($this->user, 10_000);
    $hold = $this->transactions->hold($this->user, 4_000);

    $firstResolution === 'release' ? $this->transactions->release($hold) : $this->transactions->settle($hold, 1_000);

    $this->transactions->settle($hold, 1_000);
})->throws(LogicException::class)->with(['release', 'settle']);

test('refunding usage returns the charge once', function () {
    $this->transactions->topUp($this->user, 10_000);
    $usage = $this->transactions->settle($this->transactions->hold($this->user, 4_000), 2_500);

    $this->transactions->refund($usage);
    $this->transactions->refund($usage);

    expect($this->transactions->balance($this->user))->toBe(10_000);
});

test('adjustments can move the balance either way', function () {
    $this->transactions->adjust($this->user, 5_000, 'Goodwill');
    $this->transactions->adjust($this->user, -1_000, 'Correction');

    expect($this->transactions->balance($this->user))->toBe(4_000);
});

test('transactions cannot be changed after they are recorded', function (string $operation) {
    $entry = $this->transactions->topUp($this->user, 10_000);

    $operation === 'update' ? $entry->update(['amount' => 1]) : $entry->delete();
})->throws(LogicException::class)->with(['update', 'delete']);

test('holds reference the record they reserve funds for', function () {
    $this->transactions->topUp($this->user, 10_000);
    $reference = User::factory()->create();

    $usage = $this->transactions->settle($this->transactions->hold($this->user, 4_000, $reference), 1_000);

    expect($usage->reference->is($reference))->toBeTrue();
});

test('the summary separates settled credits from funds still on hold', function () {
    $this->transactions->topUp($this->user, 10_000);
    $this->transactions->credit($this->user, 2_000);
    $usage = $this->transactions->settle($this->transactions->hold($this->user, 4_000), 3_000);
    $this->transactions->refund($usage);
    $this->transactions->settle($this->transactions->hold($this->user, 2_000), 1_500);
    $this->transactions->adjust($this->user, -500, 'Correction');
    $this->transactions->hold($this->user, 1_000);

    $summary = $this->transactions->summary($this->user);

    expect($summary->topUps)->toBe(10_000)
        ->and($summary->credits)->toBe(2_000)
        ->and($summary->usage)->toBe(4_500)
        ->and($summary->refunds)->toBe(3_000)
        ->and($summary->adjustments)->toBe(-500)
        ->and($summary->held)->toBe(1_000)
        ->and($summary->settled())->toBe(10_000)
        ->and($summary->available())->toBe(9_000)
        ->and($summary->available())->toBe($this->transactions->balance($this->user));
});
