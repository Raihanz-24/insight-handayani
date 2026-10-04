<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Services\SerpApi\SerpApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SnapshotRatingsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'serpapi.com/*' => Http::response([
                'place_info' => ['title' => 'X', 'rating' => 4.5, 'reviews' => 500],
            ], 200),
        ]);

        // Beri API key agar client dianggap terkonfigurasi.
        config()->set('serpapi.key', 'test-key');
        app()->forgetInstance(SerpApiClient::class);
    }

    public function test_force_syncs_all_active_places(): void
    {
        Place::factory()->fetchable()->create(['name' => 'A', 'analysis_mode' => Place::MODE_MANUAL]);
        Place::factory()->fetchable()->create(['name' => 'B', 'analysis_mode' => Place::MODE_MANUAL]);

        $this->artisan('analytics:snapshot-ratings', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(2, RatingSnapshot::query()->successful()->count());
    }

    public function test_scheduled_run_processes_only_due_places(): void
    {
        $due = Place::factory()->scheduled(1)->fetchable()->create(['last_synced_at' => null]);
        Place::factory()->scheduled(7)->fetchable()->create(['last_synced_at' => now()]);
        Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        $this->artisan('analytics:snapshot-ratings')->assertSuccessful();

        $this->assertSame(1, RatingSnapshot::query()->successful()->count());
        $this->assertSame($due->id, RatingSnapshot::query()->successful()->value('place_id'));
    }

    public function test_place_option_targets_single_place(): void
    {
        $place = Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);
        Place::factory()->fetchable()->create(['analysis_mode' => Place::MODE_MANUAL]);

        $this->artisan('analytics:snapshot-ratings', ['--place' => $place->id])
            ->assertSuccessful();

        $this->assertSame(1, RatingSnapshot::query()->successful()->count());
        $this->assertSame($place->id, RatingSnapshot::query()->successful()->value('place_id'));
    }
}
