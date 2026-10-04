<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu ulasan individual dari Google Maps.
 *
 * Inti analitik: `rating` (bintang 1..5) + `review_date` → distribusi bintang
 * per rentang tanggal.
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'place_id',
        'review_id',
        'review_key',
        'rating',
        'rating_raw',
        'review_date',
        'reviewed_at',
        'author_name',
        'author_id',
        'likes',
        'snippet',
        'captured_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'rating_raw' => 'float',
            'review_date' => 'date',
            'reviewed_at' => 'datetime',
            'captured_at' => 'datetime',
            'likes' => 'integer',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Place, Review> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Kunci idempoten: pakai review_id bila ada, jika tidak hash dari data stabil.
     */
    public static function keyFor(?string $reviewId, ?string $authorName, int $rating, string $reviewDate, ?string $snippet): string
    {
        if (filled($reviewId)) {
            return 'gid:'.$reviewId;
        }

        return 'h:'.sha1(implode('|', [
            (string) $authorName,
            (string) $rating,
            $reviewDate,
            substr((string) $snippet, 0, 120),
        ]));
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeForPlace(Builder $query, int $placeId): Builder
    {
        return $query->where('place_id', $placeId);
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeBetweenDates(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder
    {
        return $query->whereBetween('review_date', [
            CarbonImmutable::parse($from)->toDateString(),
            CarbonImmutable::parse($to)->toDateString(),
        ]);
    }
}
