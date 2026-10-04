<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DailyReviewStat;
use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AnalyticsDataTransferTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = storage_path('app/test-export-'.uniqid().'.json');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->file)) {
            File::delete($this->file);
        }

        parent::tearDown();
    }

    public function test_export_writes_all_tables_to_json(): void
    {
        $place = Place::factory()->fetchable()->create(['name' => 'RM Uji']);
        Review::factory()->count(3)->create(['place_id' => $place->id]);
        RatingSnapshot::factory()->create(['place_id' => $place->id]);
        DailyReviewStat::query()->create([
            'place_id' => $place->id,
            'stat_date' => now()->toDateString(),
            'new_reviews' => 3,
        ]);

        $this->artisan('analytics:export-data', ['--path' => $this->file])
            ->assertSuccessful();

        $this->assertTrue(File::exists($this->file));

        $payload = json_decode((string) File::get($this->file), true);

        $this->assertSame(1, $payload['meta']['version'] ?? null);
        $this->assertCount(1, $payload['tables']['places']);
        $this->assertCount(3, $payload['tables']['reviews']);
        $this->assertCount(1, $payload['tables']['rating_snapshots']);
        $this->assertCount(1, $payload['tables']['daily_review_stats']);
    }

    public function test_import_recreates_data_and_is_idempotent(): void
    {
        $place = Place::factory()->fetchable()->create(['name' => 'RM Uji']);
        Review::factory()->count(4)->create(['place_id' => $place->id]);
        RatingSnapshot::factory()->create(['place_id' => $place->id]);

        $this->artisan('analytics:export-data', ['--path' => $this->file])->assertSuccessful();

        // Hapus semua agar seolah server baru.
        Review::query()->delete();
        RatingSnapshot::query()->delete();
        Place::query()->delete();

        // Impor sekali.
        $this->artisan('analytics:import-data', ['--path' => $this->file])->assertSuccessful();

        $this->assertSame(4, Review::query()->count());
        $this->assertSame(1, RatingSnapshot::query()->count());
        $this->assertSame(1, Place::query()->count());

        // Impor lagi → idempoten (jumlah tetap).
        $this->artisan('analytics:import-data', ['--path' => $this->file])->assertSuccessful();

        $this->assertSame(4, Review::query()->count());
        $this->assertSame(1, RatingSnapshot::query()->count());
        $this->assertSame(1, Place::query()->count());
    }

    public function test_import_missing_file_fails(): void
    {
        $this->artisan('analytics:import-data', ['--path' => storage_path('app/tidak-ada.json')])
            ->assertFailed();
    }
}
