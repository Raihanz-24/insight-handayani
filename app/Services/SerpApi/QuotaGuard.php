<?php

declare(strict_types=1);

namespace App\Services\SerpApi;

use App\Models\SerpApiUsage;
use Carbon\CarbonImmutable;

/**
 * Guard kuota SerpApi harian.
 *
 * Tujuan: mencegah pemakaian melebihi batas (biaya / limit plan).
 * Setiap "search" = 1 request ke SerpApi (setiap halaman hasil = 1 search).
 */
class QuotaGuard
{
    public function __construct(
        private readonly int $dailyLimit = 40,
    ) {}

    /**
     * Apakah masih ada sisa kuota hari ini.
     */
    public function hasRemaining(int $cost = 1, ?CarbonImmutable $date = null): bool
    {
        return $this->usedToday($date) + $cost <= $this->dailyLimit;
    }

    /**
     * Sisa kuota hari ini.
     */
    public function remaining(?CarbonImmutable $date = null): int
    {
        return max(0, $this->dailyLimit - $this->usedToday($date));
    }

    /**
     * Pemakaian hari ini.
     */
    public function usedToday(?CarbonImmutable $date = null): int
    {
        $date = $this->normalizeDate($date);

        return (int) (SerpApiUsage::query()
            ->whereDate('usage_date', $date->toDateString())
            ->value('searches') ?? 0);
    }

    /**
     * Catat pemakaian (increment).
     */
    public function record(int $cost = 1, ?CarbonImmutable $date = null): void
    {
        if ($cost <= 0) {
            return;
        }

        $date = $this->normalizeDate($date);

        $usage = SerpApiUsage::query()->firstOrCreate(
            ['usage_date' => $date->toDateString()],
            ['searches' => 0],
        );

        $usage->increment('searches', $cost);
    }

    /**
     * Batas harian yang berlaku.
     */
    public function dailyLimit(): int
    {
        return $this->dailyLimit;
    }

    private function normalizeDate(?CarbonImmutable $date): CarbonImmutable
    {
        $date ??= CarbonImmutable::now();

        return $date->startOfDay();
    }
}
