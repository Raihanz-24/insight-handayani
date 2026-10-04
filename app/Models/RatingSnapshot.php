<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RatingSnapshotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali pengambilan rating+jumlah ulasan untuk sebuah tempat.
 */
class RatingSnapshot extends Model
{
    /** @use HasFactory<RatingSnapshotFactory> */
    use HasFactory;

    public const SOURCE_SERPAPI = 'serpapi';

    public const SOURCE_MANUAL = 'manual';

    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'place_id',
        'captured_at',
        'captured_date',
        'rating',
        'reviews_count',
        'source',
        'status',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'captured_date' => 'date',
            'rating' => 'float',
            'reviews_count' => 'integer',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Place, RatingSnapshot> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * @param  Builder<RatingSnapshot>  $query
     * @return Builder<RatingSnapshot>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OK);
    }

    /**
     * @param  Builder<RatingSnapshot>  $query
     * @return Builder<RatingSnapshot>
     */
    public function scopeBetweenDates(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder
    {
        return $query->whereBetween('captured_date', [
            CarbonImmutable::parse($from)->toDateString(),
            CarbonImmutable::parse($to)->toDateString(),
        ]);
    }
}
