<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
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

    /** Mode analisis per tempat. */
    public const MODE_OFF = 'off';

    public const MODE_MANUAL = 'manual';

    public const MODE_SCHEDULED = 'scheduled';

    protected $fillable = [
        'name',
        'maps_url',
        'latitude',
        'longitude',
        'type',
        'serpapi_data_id',
        'serpapi_place_id',
        'query',
        'is_active',
        'analysis_mode',
        'schedule_interval_days',
        'schedule_hour',
        'last_synced_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'schedule_interval_days' => 'integer',
            'schedule_hour' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Daftar mode analisis yang valid + label.
     *
     * @return array<string, string>
     */
    public static function analysisModes(): array
    {
        return [
            self::MODE_OFF => 'Nonaktif (tidak diambil)',
            self::MODE_MANUAL => 'Manual (hanya saat tombol ditekan)',
            self::MODE_SCHEDULED => 'Terjadwal (otomatis berkala)',
        ];
    }

    /**
     * Apakah tempat ini boleh diambil datanya (manual maupun terjadwal).
     */
    public function isAnalysisEnabled(): bool
    {
        return $this->is_active && $this->analysis_mode !== self::MODE_OFF;
    }

    /**
     * Apakah tempat ini dijadwalkan untuk diambil otomatis.
     */
    public function isScheduled(): bool
    {
        return $this->is_active
            && $this->analysis_mode === self::MODE_SCHEDULED
            && $this->schedule_interval_days !== null
            && $this->schedule_interval_days > 0;
    }

    /**
     * Apakah sudah waktunya diambil lagi (berdasarkan interval hari & jam).
     */
    public function isDueForSync(\DateTimeInterface|string|null $now = null): bool
    {
        if (! $this->isScheduled()) {
            return false;
        }

        $now = $now === null
            ? CarbonImmutable::now()
            : CarbonImmutable::parse($now);

        if ($this->last_synced_at === null) {
            return true;
        }

        $dueAt = $this->last_synced_at
            ->toImmutable()
            ->addDays((int) $this->schedule_interval_days);

        // Bila jadwal jam diisi, tunda sampai jam tersebut pada hari jatuh tempo.
        if ($this->schedule_hour !== null) {
            $dueAt = $dueAt->setTime((int) $this->schedule_hour, 0);
        }

        return $now->greaterThanOrEqualTo($dueAt);
    }

    /**
     * @param  Builder<Place>  $query
     * @return Builder<Place>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('analysis_mode', self::MODE_SCHEDULED)
            ->whereNotNull('schedule_interval_days')
            ->where('schedule_interval_days', '>', 0);
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
