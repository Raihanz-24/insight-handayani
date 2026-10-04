<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Place;
use App\Models\Review;
use App\Services\Analytics\Dto\RatingPeriodReport;
use Carbon\CarbonImmutable;

/**
 * Menghitung analitik distribusi bintang dari review tersimpan.
 *
 * Ini inti kebutuhan: "berapa orang memberi bintang 1..5" pada rentang tertentu,
 * dihitung dari tanggal review (bukan tanggal pengambilan).
 */
class RatingAnalyticsService
{
    /**
     * Laporan distribusi bintang untuk 1 tempat pada rentang tanggal.
     */
    public function reportForPlace(
        Place $place,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
    ): RatingPeriodReport {
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->endOfDay();

        // Distribusi bintang (5..1).
        $rows = Review::query()
            ->where('place_id', $place->id)
            ->whereBetween('review_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $distribution[$star] = (int) ($rows[$star] ?? 0);
        }

        $total = array_sum($distribution);
        $sumStars = 0;
        foreach ($distribution as $star => $count) {
            $sumStars += $star * $count;
        }

        $average = $total > 0 ? round($sumStars / $total, 2) : null;

        return new RatingPeriodReport(
            placeName: $place->name,
            from: $fromDate,
            to: $toDate,
            distribution: $distribution,
            total: $total,
            average: $average,
        );
    }

    /**
     * Laporan untuk beberapa tempat sekaligus.
     *
     * @param  iterable<Place>  $places
     * @return array<int, RatingPeriodReport>
     */
    public function reportForPlaces(iterable $places, \DateTimeInterface|string $from, \DateTimeInterface|string $to): array
    {
        $reports = [];

        foreach ($places as $place) {
            $reports[] = $this->reportForPlace($place, $from, $to);
        }

        return $reports;
    }

    /**
     * Tren jumlah review per periode (bucket harian/bulanan) untuk sebuah tempat.
     *
     * @return array<string, int> ['2026-01' => 12, ...]
     */
    public function trendForPlace(
        Place $place,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        string $granularity = 'month',
    ): array {
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->endOfDay();

        $format = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m';

        $rows = Review::query()
            ->where('place_id', $place->id)
            ->whereBetween('review_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->selectRaw("DATE_FORMAT(review_date, '{$format}') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total', 'bucket')
            ->all();

        return $rows ?? [];
    }
}
