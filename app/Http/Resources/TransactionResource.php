<?php

namespace App\Http\Resources;

use App\Enums\TransactionType;
use App\Models\Generation;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    private const LABELS = [
        'top_up' => 'Top-up',
        'credit' => 'Credit',
        'hold' => 'Reserved',
        'release' => 'Released',
        'usage' => 'Generation',
        'refund' => 'Refund',
        'adjustment' => 'Adjustment',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $generation = $this->reference instanceof Generation ? $this->reference : null;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => self::LABELS[$this->type->value],
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'isPending' => $this->type === TransactionType::Hold && ! $this->isResolvedHold(),
            'generation' => $generation === null ? null : [
                'url' => route('generations.show', $generation),
                'prompt' => $generation->prompt,
            ],
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }

    private function isResolvedHold(): bool
    {
        return (bool) ($this->resolved_entries_exists ?? false);
    }
}
