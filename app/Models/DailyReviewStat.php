<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ringkasan review HARIAN per tempat.
 *
 * Menjawab: "pada hari X, berapa orang memberi ★1..★5?"
 * Berdasarkan review BARU yang tersimpan pada hari itu, plus validasi dari
 * selisih total ulasan Google (snapshot).
 */
class DailyReviewStat extends Model
{
    protected $fillable = [
        'place_id',
        'stat_date',
        'new_reviews',
        'star_1',
        'star_2',
        'star_3',
        'star_4',
        'star_5',
        'total_reviews',
        'reviews_delta',
        'average_rating',
        'synced_total',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'new_reviews' => 'integer',
            'star_1' => 'integer',
            'star_2' => 'integer',
            'star_3' => 'integer',
            'star_4' => 'integer',
            'star_5' => 'integer',
            'total_reviews' => 'integer',
            'reviews_delta' => 'integer',
            'average_rating' => 'float',
            'synced_total' => 'integer',
        ];
    }

    /** @return BelongsTo<Place, DailyReviewStat> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /**
     * Distribusi bintang (5..1) untuk hari ini.
     *
     * @return array<int, int>
     */
    public function distribution(): array
    {
        return [
            5 => $this->star_5,
            4 => $this->star_4,
            3 => $this->star_3,
            2 => $this->star_2,
            1 => $this->star_1,
        ];
    }

    /**
     * @param  Builder<DailyReviewStat>  $query
     * @return Builder<DailyReviewStat>
     */
    public function scopeBetweenDates(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder
    {
        return $query->whereBetween('stat_date', [
            CarbonImmutable::parse($from)->toDateString(),
            CarbonImmutable::parse($to)->toDateString(),
        ]);
    }
}
