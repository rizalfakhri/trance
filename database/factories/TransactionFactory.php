<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TransactionType::TopUp,
            'amount' => 10_000_000_000,
            'currency' => 'usd',
        ];
    }

    public function topUp(int $amount): static
    {
        return $this->state(fn (): array => ['type' => TransactionType::TopUp, 'amount' => $amount]);
    }
}
