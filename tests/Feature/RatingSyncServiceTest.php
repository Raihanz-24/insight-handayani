<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailyReviewStat;
use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Models\Review;
use App\Models\SerpApiUsage;
use App\Services\Analytics\RatingSyncService;
use App\Services\SerpApi\QuotaGuard;
use App\Services\SerpApi\SerpApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RatingSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(int $dailyLimit = 40, bool $configured = true): RatingSyncService
    {
        $client = new SerpApiClient(
            apiKey: $configured ? 'test-key' : '',
            retry: 0,
            timeout: 5,
        );

        return new RatingSyncService($client, new QuotaGuard(dailyLimit: $dailyLimit));
    }

    public function test_mode_off_records_error_snapshot(): void
    {
        $place = Place::factory()->off()->fetchable()->create();

        $snapshot = $this->service()->syncPlace($place);

        $this->assertSame(RatingSnapshot::STATUS_ERROR, $snapshot->status);
        $this->assertStringContainsString('dinonaktifkan', (string) $snapshot->error_message);
    }

    public function test_force_overrides_off_mode(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response([
                'place_info' => ['title' => 'X', 'rating' => 4.2, 'reviews' => 100],
            ], 200),
        ]);

        $place = Place::factory()->off()->fetchable()->create();

        $snapshot = $this->service()->syncPlace($place, force: true);

        $this->assertSame(RatingSnapshot::STATUS_OK, $snapshot->status);
        $this->assertSame(4.2, $snapshot->rating);
    }

    public function test_successful_sync_stores_snapshot_and_updates_last_synced(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response([
                'place_info' => ['title' => 'RM', 'rating' => 4.6, 'reviews' => 1591],
            ], 200),
        ]);

        $place = Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        // Tanpa review individual -> hanya 1 search (rating).
        $snapshot = $this->service()->syncPlace($place, withReviews: false);

        $this->assertSame(RatingSnapshot::STATUS_OK, $snapshot->status);
        $this->assertSame(4.6, $snapshot->rating);
        $this->assertSame(1591, $snapshot->reviews_count);
        $this->assertNotNull($place->fresh()->last_synced_at);

        // Kuota tercatat 1 (hanya ringkasan rating).
        $this->assertSame(1, (int) SerpApiUsage::query()->value('searches'));
    }

    public function test_sync_with_reviews_stores_individual_reviews(): void
    {
        // Dengan perbaikan hemat kuota, review halaman pertama ikut pada respons
        // fetchRating → hanya 1 request HTTP (bukan 2).
        Http::fake([
            'serpapi.com/*' => Http::response([
                'place_info' => ['title' => 'RM', 'rating' => 4.6, 'reviews' => 1591],
                'reviews' => [
                    ['rating' => 5.0, 'iso_date' => '2026-06-10T10:00:00Z', 'review_id' => 'a', 'user' => ['name' => 'A']],
                    ['rating' => 3.0, 'iso_date' => '2026-06-09T10:00:00Z', 'review_id' => 'b', 'user' => ['name' => 'B']],
                ],
                'serpapi_pagination' => [],
            ], 200),
        ]);

        $place = Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        $this->service()->syncPlace($place, withReviews: true);

        $this->assertSame(2, Review::query()->where('place_id', $place->id)->count());
        $this->assertSame(2, $place->fresh()->reviews_synced);
        $this->assertSame('2026-06-09', $place->fresh()->oldest_review_date->toDateString());
        $this->assertSame('2026-06-10', $place->fresh()->newest_review_date->toDateString());

        // Hemat kuota: hanya 1 search (halaman 1 memuat rating + review sekaligus).
        $this->assertSame(1, (int) SerpApiUsage::query()->value('searches'));
        Http::assertSentCount(1);
    }

    public function test_sync_reviews_is_idempotent(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response([
                'reviews' => [
                    ['rating' => 5.0, 'iso_date' => '2026-06-10T10:00:00Z', 'review_id' => 'same', 'user' => ['name' => 'A']],
                ],
                'serpapi_pagination' => [],
            ], 200),
        ]);

        $place = Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        $this->service()->syncReviews($place);
        $this->service()->syncReviews($place);

        // Tetap 1 meski diambil 2x (idempoten via review_key).
        $this->assertSame(1, Review::query()->where('place_id', $place->id)->count());
    }

    public function test_sync_records_daily_review_stats_with_star_breakdown(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response([
                'place_info' => ['title' => 'RM', 'rating' => 4.5, 'reviews' => 1000],
                'reviews' => [
                    ['rating' => 5.0, 'iso_date' => '2026-06-10T10:00:00Z', 'review_id' => 's5', 'user' => ['name' => 'A']],
                    ['rating' => 5.0, 'iso_date' => '2026-06-09T10:00:00Z', 'review_id' => 's5b', 'user' => ['name' => 'B']],
                    ['rating' => 2.0, 'iso_date' => '2026-06-08T10:00:00Z', 'review_id' => 's2', 'user' => ['name' => 'C']],
                ],
                'serpapi_pagination' => [],
            ], 200),
        ]);

        $place = Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        $this->service()->syncPlace($place, withReviews: true);

        $stat = DailyReviewStat::query()->where('place_id', $place->id)->first();

        $this->assertNotNull($stat);
        $this->assertSame(3, $stat->new_reviews);
        $this->assertSame(2, $stat->star_5);
        $this->assertSame(0, $stat->star_4);
        $this->assertSame(1, $stat->star_2);
        $this->assertSame(1000, $stat->total_reviews);
        $this->assertSame(4.5, $stat->average_rating);
        // Hari pertama → belum ada snapshot sebelumnya → delta null.
        $this->assertNull($stat->reviews_delta);
    }

    public function test_daily_stats_compute_delta_from_previous_snapshot(): void
    {
        // Snapshot kemarin: 1000 ulasan.
        RatingSnapshot::query()->create([
            'place_id' => ($place = Place::factory()->fetchable()->create())->id,
            'captured_at' => '2026-06-09 00:05:00',
            'captured_date' => '2026-06-09',
            'rating' => 4.5,
            'reviews_count' => 1000,
            'source' => RatingSnapshot::SOURCE_SERPAPI,
            'status' => RatingSnapshot::STATUS_OK,
        ]);

        // Hari ini: 1007 ulasan → delta +7.
        $snapshot = RatingSnapshot::query()->create([
            'place_id' => $place->id,
            'captured_at' => '2026-06-10 00:05:00',
            'captured_date' => '2026-06-10',
            'rating' => 4.6,
            'reviews_count' => 1007,
            'source' => RatingSnapshot::SOURCE_SERPAPI,
            'status' => RatingSnapshot::STATUS_OK,
        ]);

        $stat = $this->service()->recordDailyStat(
            $place,
            CarbonImmutable::parse('2026-06-10 00:05:00'),
            $snapshot,
            [1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => 2],
        );

        $this->assertSame(3, $stat->new_reviews);
        $this->assertSame(7, $stat->reviews_delta); // 1007 - 1000
    }

    public function test_quota_exhausted_blocks_fetch(): void
    {
        Http::fake();

        $place = Place::factory()->fetchable()->create();

        $snapshot = $this->service(dailyLimit: 0)->syncPlace($place);

        $this->assertSame(RatingSnapshot::STATUS_ERROR, $snapshot->status);
        $this->assertStringContainsString('Kuota', (string) $snapshot->error_message);
        Http::assertNothingSent();
    }

    public function test_sync_due_only_processes_due_places(): void
    {
        Http::fake([
            'serpapi.com/*' => Http::response([
                'place_info' => ['title' => 'X', 'rating' => 4.0, 'reviews' => 10],
            ], 200),
        ]);

        // Due (belum pernah sync).
        $due = Place::factory()->scheduled(1)->fetchable()->create(['last_synced_at' => null]);
        // Belum due (baru sync, interval 7 hari).
        Place::factory()->scheduled(7)->fetchable()->create(['last_synced_at' => now()]);
        // Mode manual (bukan scheduled) -> diabaikan.
        Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        $snapshots = $this->service()->syncDue();

        $this->assertCount(1, $snapshots);
        $this->assertSame($due->id, $snapshots[0]->place_id);
    }
}
