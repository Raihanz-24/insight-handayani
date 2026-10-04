<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lokasi yang dipantau rating-nya di Google Maps.
 */
class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    public const TYPE_RESTAURANT = 'restaurant';

    public const TYPE_COTTAGE = 'cottage';

    protected $fillable = [
        'name',
        'type',
        'serpapi_data_id',
        'serpapi_place_id',
        'query',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<RatingSnapshot> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(RatingSnapshot::class);
    }

    /** Snapshot terbaru (yang berhasil) untuk tempat ini. */
    public function latestSnapshot(): ?RatingSnapshot
    {
        return $this->snapshots()
            ->where('status', RatingSnapshot::STATUS_OK)
            ->latest('captured_at')
            ->first();
    }

    /**
     * Apakah tempat punya penanda yang cukup untuk diambil otomatis.
     */
    public function isFetchable(): bool
    {
        return filled($this->serpapi_data_id) || filled($this->serpapi_place_id);
    }

    /**
     * @param  Builder<Place>  $query
     * @return Builder<Place>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Place>  $query
     * @return Builder<Place>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
