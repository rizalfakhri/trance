<?php

namespace App\Models;

use App\Data\GenerationRequest;
use App\Data\ProviderGeneration;
use App\Data\ReferenceAsset;
use App\Enums\AssetRole;
use App\Enums\GenerationStatus;
use App\Enums\Modality;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'modality',
        'prompt',
        'options',
        'reference_urls',
        'status',
        'attempt',
        'external_id',
        'quote_total',
        'fee_basis_points',
        'hold_transaction_id',
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
        'additional_units',
        'rates',
        'cost_total',
        'fee_total',
        'charge_total',
        'error_code',
        'error_message',
        'submitted_at',
        'started_at',
        'completed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'queued',
        'attempt' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modality' => Modality::class,
            'status' => GenerationStatus::class,
            'attempt' => 'integer',
            'options' => 'array',
            'reference_urls' => 'array',
            'additional_units' => 'array',
            'rates' => 'array',
            'submitted_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
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
        return $this->belongsTo(Transaction::class, 'hold_transaction_id');
    }

    /**
     * @return HasMany<GenerationAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(GenerationAsset::class);
    }

    /**
     * @return HasMany<GenerationAsset, $this>
     */
    public function references(): HasMany
    {
        return $this->assets()->where('role', AssetRole::Reference)->orderBy('position');
    }

    /**
     * @return HasMany<GenerationAsset, $this>
     */
    public function outputs(): HasMany
    {
        return $this->assets()->where('role', AssetRole::Output)->orderBy('position');
    }

    /**
     * @return HasMany<GenerationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GenerationEvent::class)->orderBy('occurred_at');
    }

    /**
     * Estimated (held quote) and actual (final charge) cost in nano-dollars; failed generations
     * cost nothing and pending ones have no actual cost yet.
     *
     * @return array{estimate: int|null, actual: int|null, currency: string}
     */
    public function costSummary(): array
    {
        return [
            'estimate' => $this->quote_total,
            'actual' => $this->status === GenerationStatus::Failed ? 0 : $this->charge_total,
            'currency' => $this->rates['currency'] ?? 'usd',
        ];
    }

    /**
     * @return list<ReferenceAsset>
     */
    public function referenceUrlAssets(): array
    {
        return array_map(fn (string $url): ReferenceAsset => new ReferenceAsset($url, 'image/*'), $this->reference_urls ?? []);
    }

    /**
     * @param  list<ReferenceAsset>  $references
     */
    public function toProviderRequest(array $references = []): GenerationRequest
    {
        return new GenerationRequest(
            prompt: $this->prompt,
            modality: $this->modality,
            model: $this->model,
            options: $this->options,
            references: $references,
            metadata: [
                GenerationRequest::GENERATION_ID_METADATA_KEY => $this->getKey(),
                GenerationRequest::ATTEMPT_METADATA_KEY => (string) $this->attempt,
            ],
            idempotencyKey: $this->attempt === 1 ? $this->getKey() : "{$this->getKey()}:{$this->attempt}",
        );
    }

    public static function resolveFromProvider(string $provider, ProviderGeneration $generation): ?self
    {
        $byExternalId = static::query()
            ->where('provider', $provider)
            ->where('external_id', $generation->externalId)
            ->first();

        if ($byExternalId !== null || $generation->generationId() === null) {
            return $byExternalId;
        }

        return static::query()
            ->where('provider', $provider)
            ->whereKey($generation->generationId())
            ->first();
    }
}
