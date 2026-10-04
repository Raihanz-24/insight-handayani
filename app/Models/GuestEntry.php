<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GuestEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jumlah kendaraan yang masuk pada satu minggu (input manual).
 *
 * Satu baris = satu minggu. `week_start` selalu hari SENIN (dinormalkan).
 */
class GuestEntry extends Model
{
    /** @use HasFactory<GuestEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'week_start',
        'week_end',
        'vehicles',
        'note',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'vehicles' => 'integer',
        ];
    }

    /** @return BelongsTo<User, GuestEntry> */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * Normalisasi tanggal apa pun ke SENIN sebagai awal minggu.
     */
    public static function normalizeWeekStart(\DateTimeInterface|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->startOfDay();
    }

    /**
     * Tetapkan week_start & week_end dari satu tanggal acuan.
     */
    public function setWeekFromDate(\DateTimeInterface|string $date): static
    {
        $start = static::normalizeWeekStart($date);

        $this->week_start = $start->toDateString();
        $this->week_end = $start->addDays(6)->toDateString();

        return $this;
    }

    /**
     * @param  Builder<GuestEntry>  $query
     * @return Builder<GuestEntry>
     */
    public function scopeBetweenDates(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder
    {
        return $query->whereBetween('week_start', [
            CarbonImmutable::parse($from)->toDateString(),
            CarbonImmutable::parse($to)->toDateString(),
        ]);
    }
}
