<?php

namespace App\Models;

use App\Enums\AssetRole;
use Database\Factories\GenerationAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationAsset extends Model
{
    /** @use HasFactory<GenerationAssetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'generation_id',
        'role',
        'position',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'duration_seconds',
        'source_url',
        'source_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AssetRole::class,
            'position' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'float',
            'source_expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
