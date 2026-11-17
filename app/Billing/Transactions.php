<?php

namespace App\Billing;

use App\Data\BalanceSummary;
use App\Enums\TransactionType;
use App\Exceptions\Billing\InsufficientBalanceException;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class Transactions
{
    public function balance(User $user): int
    {
        return (int) $user->transactions()->sum('amount');
    }

    public function summary(User $user): BalanceSummary
    {
        /** @var array<string, int> $totals */
        $totals = $user->transactions()
            ->selectRaw('type, sum(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $total = fn (TransactionType $type): int => $totals[$type->value] ?? 0;

        return new BalanceSummary(
            topUps: $total(TransactionType::TopUp),
            credits: $total(TransactionType::Credit),
            usage: -$total(TransactionType::Usage),
            refunds: $total(TransactionType::Refund),
            adjustments: $total(TransactionType::Adjustment),
            held: -($total(TransactionType::Hold) + $total(TransactionType::Release)),
        );
    }

    public function topUp(User $user, int $amount, ?string $description = null, ?string $idempotencyKey = null, ?Model $reference = null): Transaction
    {
        return $this->record($user, TransactionType::TopUp, $this->positive($amount), $description, $idempotencyKey, $reference);
    }

    public function credit(User $user, int $amount, ?string $description = null, ?string $idempotencyKey = null): Transaction
    {
        return $this->record($user, TransactionType::Credit, $this->positive($amount), $description, $idempotencyKey);
    }

    public function adjust(User $user, int $signedAmount, string $description): Transaction
    {
        if ($signedAmount === 0) {
            throw new InvalidArgumentException('Adjustments must change the balance.');
        }

        return $this->record($user, TransactionType::Adjustment, $signedAmount, $description);
    }

    /**
     * @throws InsufficientBalanceException
     */
    public function hold(User $user, int $amount, ?Model $reference = null, ?string $description = null): Transaction
    {
        $amount = $this->positive($amount);

        return DB::transaction(function () use ($user, $amount, $reference, $description): Transaction {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $available = $this->balance($user);

            if ($available < $amount) {
                throw new InsufficientBalanceException($amount, $available);
            }

            return $this->record($user, TransactionType::Hold, -$amount, $description, reference: $reference);
        });
    }

    public function release(Transaction $hold): Transaction
    {
        return DB::transaction(function () use ($hold): Transaction {
            $this->ensureUnresolvedHold($hold);

            return $this->resolve($hold, TransactionType::Release, -$hold->amount);
        });
    }

    /**
     * Converts a hold into the actual charge: the hold is released and the usage is recorded.
     */
    public function settle(Transaction $hold, int $actualAmount, ?string $description = null): Transaction
    {
        if ($actualAmount < 0) {
            throw new InvalidArgumentException('Usage cannot be negative.');
        }

        return DB::transaction(function () use ($hold, $actualAmount, $description): Transaction {
            $this->ensureUnresolvedHold($hold);
            $this->resolve($hold, TransactionType::Release, -$hold->amount);

            return $this->resolve($hold, TransactionType::Usage, -$actualAmount, $description);
        });
    }

    public function refund(Transaction $usage, ?string $description = null): Transaction
    {
        if ($usage->type !== TransactionType::Usage) {
            throw new LogicException('Only usage entries can be refunded.');
        }

        return $this->record(
            $usage->user,
            TransactionType::Refund,
            -$usage->amount,
            $description,
            "refund:{$usage->getKey()}",
            $usage->reference,
        );
    }

    private function positive(int $amount): int
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        return $amount;
    }

    private function ensureUnresolvedHold(Transaction $hold): void
    {
        if ($hold->type !== TransactionType::Hold) {
            throw new LogicException('Only holds can be released or settled.');
        }

        if (Transaction::query()->where('hold_id', $hold->getKey())->exists()) {
            throw new LogicException('This hold has already been released or settled.');
        }
    }

    private function resolve(Transaction $hold, TransactionType $type, int $amount, ?string $description = null): Transaction
    {
        return Transaction::query()->create([
            'user_id' => $hold->user_id,
            'type' => $type,
            'amount' => $amount,
            'currency' => $hold->currency,
            'description' => $description,
            'reference_type' => $hold->reference_type,
            'reference_id' => $hold->reference_id,
            'hold_id' => $hold->getKey(),
        ]);
    }

    private function record(
        User $user,
        TransactionType $type,
        int $amount,
        ?string $description = null,
        ?string $idempotencyKey = null,
        ?Model $reference = null,
    ): Transaction {
        $attributes = [
            'user_id' => $user->getKey(),
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
        ];

        if ($idempotencyKey === null) {
            return Transaction::query()->create($attributes);
        }

        return Transaction::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], $attributes);
    }
}
