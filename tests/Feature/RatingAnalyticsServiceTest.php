<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Models\Review;
use App\Services\Analytics\RatingAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_counts_people_per_star_in_range(): void
    {
        $place = Place::factory()->create();

        // 5 orang bintang 5, 2 orang bintang 3, 1 orang bintang 1 — semua di Juni.
        Review::factory()->for($place)->stars(5)->onDate('2026-06-05')->count(5)->create();
        Review::factory()->for($place)->stars(3)->onDate('2026-06-10')->count(2)->create();
        Review::factory()->for($place)->stars(1)->onDate('2026-06-20')->create();

        // Di luar rentang (Juli) — harus diabaikan.
        Review::factory()->for($place)->stars(5)->onDate('2026-07-02')->count(4)->create();

        $report = app(RatingAnalyticsService::class)
            ->reportForPlace($place, '2026-06-01', '2026-06-30');

        $this->assertSame(5, $report->distribution[5]);
        $this->assertSame(0, $report->distribution[4]);
        $this->assertSame(2, $report->distribution[3]);
        $this->assertSame(0, $report->distribution[2]);
        $this->assertSame(1, $report->distribution[1]);
        $this->assertSame(8, $report->total);
        // (5*5 + 3*2 + 1*1) / 8 = 32/8 = 4.0
        $this->assertSame(4.0, $report->average);
    }

    public function test_percentages(): void
    {
        $place = Place::factory()->create();

        Review::factory()->for($place)->stars(5)->onDate('2026-06-05')->count(3)->create();
        Review::factory()->for($place)->stars(1)->onDate('2026-06-06')->create();

        $report = app(RatingAnalyticsService::class)
            ->reportForPlace($place, '2026-06-01', '2026-06-30');

        $pct = $report->percentages();
        $this->assertSame(75.0, $pct[5]);
        $this->assertSame(25.0, $pct[1]);
    }

    public function test_empty_range_returns_zero(): void
    {
        $place = Place::factory()->create();

        $report = app(RatingAnalyticsService::class)
            ->reportForPlace($place, '2026-01-01', '2026-01-31');

        $this->assertSame(0, $report->total);
        $this->assertNull($report->average);
        $this->assertSame([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], $report->distribution);
    }

    public function test_trend_monthly(): void
    {
        $place = Place::factory()->create();

        Review::factory()->for($place)->onDate('2026-06-01')->count(2)->create();
        Review::factory()->for($place)->onDate('2026-07-01')->count(3)->create();

        $trend = app(RatingAnalyticsService::class)
            ->trendForPlace($place, '2026-06-01', '2026-07-31', 'month');

        $this->assertSame(2, $trend['2026-06'] ?? null);
        $this->assertSame(3, $trend['2026-07'] ?? null);
    }

    // -----------------------------------------------------------------
    // Tren dari snapshot harian (rating + total ulasan)
    // -----------------------------------------------------------------

    public function test_snapshot_trend_returns_rating_and_reviews_per_day(): void
    {
        $place = Place::factory()->create();

        RatingSnapshot::query()->create([
            'place_id' => $place->id,
            'captured_at' => '2026-07-10 00:05:00',
            'captured_date' => '2026-07-10',
            'rating' => 4.6,
            'reviews_count' => 3354,
            'source' => RatingSnapshot::SOURCE_SERPAPI,
            'status' => RatingSnapshot::STATUS_OK,
        ]);

        RatingSnapshot::query()->create([
            'place_id' => $place->id,
            'captured_at' => '2026-07-11 00:05:00',
            'captured_date' => '2026-07-11',
            'rating' => 4.7,
            'reviews_count' => 3361,
            'source' => RatingSnapshot::SOURCE_SERPAPI,
            'status' => RatingSnapshot::STATUS_OK,
        ]);

        // Snapshot error (gagal) harus diabaikan.
        RatingSnapshot::query()->create([
            'place_id' => $place->id,
            'captured_at' => '2026-07-12 00:05:00',
            'captured_date' => '2026-07-12',
            'rating' => null,
            'reviews_count' => null,
            'source' => RatingSnapshot::SOURCE_SERPAPI,
            'status' => RatingSnapshot::STATUS_ERROR,
            'error_message' => 'x',
        ]);

        $trend = app(RatingAnalyticsService::class)
            ->snapshotTrend($place, '2026-07-01', '2026-07-31');

        $this->assertCount(2, $trend);
        $this->assertSame('2026-07-10', $trend[0]['date']);
        $this->assertSame(4.6, $trend[0]['rating']);
        $this->assertSame(3354, $trend[0]['reviews']);
        $this->assertSame('2026-07-11', $trend[1]['date']);
        $this->assertSame(4.7, $trend[1]['rating']);
    }

    public function test_daily_star_distribution_builds_stacked_series(): void
    {
        $place = Place::factory()->create();

        Review::factory()->for($place)->stars(5)->onDate('2026-06-01')->count(2)->create();
        Review::factory()->for($place)->stars(4)->onDate('2026-06-01')->create();
        Review::factory()->for($place)->stars(5)->onDate('2026-06-02')->create();

        $ds = app(RatingAnalyticsService::class)
            ->dailyStarDistribution($place, '2026-06-01', '2026-06-30');

        $this->assertSame(['2026-06-01', '2026-06-02'], $ds['labels']);

        // Series pertama = bintang 5 → [2, 1]
        $this->assertSame('5 bintang', $ds['series'][0]['name']);
        $this->assertSame([2, 1], $ds['series'][0]['data']);

        // Bintang 4 hanya di hari pertama → [1, 0]
        $this->assertSame('4 bintang', $ds['series'][1]['name']);
        $this->assertSame([1, 0], $ds['series'][1]['data']);
    }

    public function test_snapshot_recap_weekly_with_delta(): void
    {
        $place = Place::factory()->create();

        // Minggu 1 (Senin 2026-07-06): 3354 → Minggu 2: 3361 (naik 7).
        foreach ([['2026-07-07', 4.6, 3354], ['2026-07-09', 4.6, 3360], ['2026-07-14', 4.7, 3361]] as [$d, $r, $c]) {
            RatingSnapshot::query()->create([
                'place_id' => $place->id,
                'captured_at' => $d.' 00:05:00',
                'captured_date' => $d,
                'rating' => $r,
                'reviews_count' => $c,
                'source' => RatingSnapshot::SOURCE_SERPAPI,
                'status' => RatingSnapshot::STATUS_OK,
            ]);
        }

        $recap = app(RatingAnalyticsService::class)
            ->snapshotRecap($place, '2026-07-01', '2026-07-31', 'week');

        $this->assertCount(2, $recap);
        $this->assertSame('2026-07-06', $recap[0]['key']);
        $this->assertSame(3360, $recap[0]['end_reviews']);
        $this->assertSame(0, $recap[0]['delta']); // belum ada pembanding
        $this->assertSame('2026-07-13', $recap[1]['key']);
        $this->assertSame(3361, $recap[1]['end_reviews']);
        $this->assertSame(1, $recap[1]['delta']); // 3361 - 3360
    }
}
