<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Models\SerpApiUsage;
use App\Services\Analytics\RatingSyncService;
use App\Services\SerpApi\QuotaGuard;
use App\Services\SerpApi\SerpApiClient;
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

        $snapshot = $this->service()->syncPlace($place);

        $this->assertSame(RatingSnapshot::STATUS_OK, $snapshot->status);
        $this->assertSame(4.6, $snapshot->rating);
        $this->assertSame(1591, $snapshot->reviews_count);
        $this->assertNotNull($place->fresh()->last_synced_at);

        // Kuota tercatat 1.
        $this->assertSame(1, (int) SerpApiUsage::query()->value('searches'));
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
