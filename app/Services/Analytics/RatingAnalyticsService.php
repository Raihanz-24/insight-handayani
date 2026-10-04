<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Place;
use App\Models\RatingSnapshot;
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

    /**
     * Tren HARIAN dari SNAPSHOT (akurat): rating rata-rata & total ulasan
     * untuk setiap tanggal capture dalam rentang.
     *
     * @return array<int, array{date: string, rating: ?float, reviews: ?int}> diurutkan menaik
     */
    public function snapshotTrend(Place $place, \DateTimeInterface|string $from, \DateTimeInterface|string $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        return RatingSnapshot::query()
            ->where('place_id', $place->id)
            ->successful()
            ->whereBetween('captured_date', [$fromDate, $toDate])
            ->orderBy('captured_date')
            ->get(['captured_date', 'rating', 'reviews_count'])
            ->map(fn ($s): array => [
                'date' => $s->captured_date->toDateString(),
                'rating' => $s->rating,
                'reviews' => $s->reviews_count,
            ])
            ->all();
    }

    /**
     * Distribusi bintang PER HARI berdasarkan tanggal review (dari review tersimpan).
     * Dipakai untuk grafik bertumpuk (stacked) jumlah orang per bintang per hari.
     *
     * @return array{sets: array<int, array<string, mixed>>, labels: array<int, string>, series: array<int, array<string, mixed>>}
     */
    public function dailyStarDistribution(Place $place, \DateTimeInterface|string $from, \DateTimeInterface|string $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $rows = Review::query()
            ->where('place_id', $place->id)
            ->whereBetween('review_date', [$fromDate, $toDate])
            ->selectRaw('review_date as d, rating, COUNT(*) as total')
            ->groupBy('d', 'rating')
            ->orderBy('d')
            ->get();

        $labels = [];
        $grid = [];

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->d)->toDateString();
            $labels[$date] = true;
            $grid[$date][(int) $row->rating] = (int) $row->total;
        }

        $labels = array_keys($labels);
        sort($labels);

        $series = [];
        for ($star = 5; $star >= 1; $star--) {
            $series[] = [
                'name' => $star.' bintang',
                'data' => array_map(fn (string $d): int => (int) ($grid[$d][$star] ?? 0), $labels),
            ];
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Rekap periode (minggu/bulan) dari snapshot: rating rata-rata & total ulasan
     * pada akhir tiap bucket, serta pertambahan ulasan.
     *
     * @param  string  $granularity  'week' | 'month'
     * @return array<int, array{key: string, label: string, rating: ?float, end_reviews: ?int, delta: int}>
     */
    public function snapshotRecap(Place $place, \DateTimeInterface|string $from, \DateTimeInterface|string $to, string $granularity = 'week'): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $snapshots = RatingSnapshot::query()
            ->where('place_id', $place->id)
            ->successful()
            ->whereBetween('captured_date', [$fromDate, $toDate])
            ->orderBy('captured_date')
            ->get(['captured_date', 'rating', 'reviews_count']);

        $buckets = [];

        foreach ($snapshots as $s) {
            $date = $s->captured_date;
            $key = $granularity === 'week'
                ? $date->copy()->startOfWeek(CarbonImmutable::MONDAY)->toDateString()
                : $date->format('Y-m');

            $buckets[$key] ??= [
                'key' => $key,
                'label' => $granularity === 'week'
                    ? 'Minggu '.$date->copy()->startOfWeek(CarbonImmutable::MONDAY)->translatedFormat('d M')
                    : $date->translatedFormat('F Y'),
                'ratings' => [],
                'reviews' => [],
            ];

            $buckets[$key]['ratings'][] = $s->rating;
            $buckets[$key]['reviews'][] = $s->reviews_count;
        }

        $out = [];
        $prevEnd = null;

        foreach ($buckets as $b) {
            $ratings = array_filter($b['ratings'], fn ($v) => $v !== null);
            $reviewVals = array_filter($b['reviews'], fn ($v) => $v !== null);
            $endReviews = $reviewVals === [] ? null : (int) end($reviewVals);

            $out[] = [
                'key' => $b['key'],
                'label' => $b['label'],
                'rating' => $ratings === [] ? null : round(array_sum($ratings) / count($ratings), 2),
                'end_reviews' => $endReviews,
                'delta' => ($endReviews !== null && $prevEnd !== null) ? $endReviews - $prevEnd : 0,
            ];

            if ($endReviews !== null) {
                $prevEnd = $endReviews;
            }
        }

        return $out;
    }
}
