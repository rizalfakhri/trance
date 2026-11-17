<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'currency' => 'usd',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'description',
        'reference_type',
        'reference_id',
        'hold_id',
        'idempotency_key',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Transactions are immutable.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function hold(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'hold_id');
    }

    /**
     * Releases and usage entries that resolved this hold.
     *
     * @return HasMany<Transaction, $this>
     */
    public function resolvedEntries(): HasMany
    {
        return $this->hasMany(Transaction::class, 'hold_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
