<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
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
}
