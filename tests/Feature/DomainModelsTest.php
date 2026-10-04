<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GuestEntry;
use App\Models\Place;
use App\Models\RatingSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelsTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Place
    // -----------------------------------------------------------------

    public function test_place_type_scopes_and_fetchable(): void
    {
        $resto = Place::factory()->restaurant()->create();
        $cottage = Place::factory()->cottage()->create();

        $this->assertSame(1, Place::query()->ofType(Place::TYPE_RESTAURANT)->count());
        $this->assertSame(1, Place::query()->ofType(Place::TYPE_COTTAGE)->count());
        $this->assertSame($resto->id, Place::query()->ofType(Place::TYPE_RESTAURANT)->first()->id);
        $this->assertSame($cottage->id, Place::query()->ofType(Place::TYPE_COTTAGE)->first()->id);

        // Tanpa data_id/place_id -> belum fetchable.
        $this->assertFalse($resto->isFetchable());

        $fetchable = Place::factory()->fetchable()->create();
        $this->assertTrue($fetchable->isFetchable());
    }

    public function test_place_active_scope(): void
    {
        Place::factory()->count(3)->create();
        Place::factory()->inactive()->create();

        $this->assertSame(3, Place::query()->active()->count());
        $this->assertSame(4, Place::query()->count());
    }

    public function test_place_latest_snapshot_ignores_errors(): void
    {
        $place = Place::factory()->create();

        RatingSnapshot::factory()->for($place)->create([
            'captured_at' => now()->subDays(2),
            'captured_date' => now()->subDays(2)->toDateString(),
            'rating' => 4.0,
        ]);

        // Snapshot lebih baru tapi GAGAL -> harus diabaikan.
        RatingSnapshot::factory()->for($place)->error()->create([
            'captured_at' => now(),
            'captured_date' => now()->toDateString(),
        ]);

        $latest = $place->latestSnapshot();

        $this->assertNotNull($latest);
        $this->assertSame(4.0, $latest->rating);
    }

    // -----------------------------------------------------------------
    // GuestEntry — normalisasi minggu
    // -----------------------------------------------------------------

    public function test_guest_entry_normalizes_week_start_to_monday(): void
    {
        // 2026-01-07 adalah Rabu.
        $entry = new GuestEntry;
        $entry->setWeekFromDate('2026-01-07');

        $this->assertSame('2026-01-05', $entry->week_start->toDateString()); // Senin
        $this->assertSame('2026-01-11', $entry->week_end->toDateString());   // Minggu
    }

    public function test_guest_entry_week_unique_constraint(): void
    {
        GuestEntry::factory()->create(['week_start' => '2026-01-05', 'week_end' => '2026-01-11']);

        $this->expectException(QueryException::class);

        GuestEntry::factory()->create(['week_start' => '2026-01-05', 'week_end' => '2026-01-11']);
    }

    public function test_guest_entry_between_dates_scope(): void
    {
        GuestEntry::factory()->create(['week_start' => '2026-01-05', 'week_end' => '2026-01-11']);
        GuestEntry::factory()->create(['week_start' => '2026-02-02', 'week_end' => '2026-02-08']);

        $count = GuestEntry::query()->betweenDates('2026-01-01', '2026-01-31')->count();

        $this->assertSame(1, $count);
    }

    // -----------------------------------------------------------------
    // RatingSnapshot
    // -----------------------------------------------------------------

    public function test_rating_snapshot_relations_and_scopes(): void
    {
        $place = Place::factory()->create();

        RatingSnapshot::factory()->for($place)->create(['captured_date' => '2026-01-10']);
        RatingSnapshot::factory()->for($place)->error()->create(['captured_date' => '2026-01-11']);

        $this->assertSame(2, $place->snapshots()->count());
        $this->assertSame(1, RatingSnapshot::query()->successful()->count());
        $this->assertSame(1, RatingSnapshot::query()->betweenDates('2026-01-01', '2026-01-31')->successful()->count());
        $this->assertInstanceOf(Place::class, RatingSnapshot::query()->successful()->first()->place);
    }

    public function test_rating_snapshot_unique_per_place_day_source(): void
    {
        $place = Place::factory()->create();

        RatingSnapshot::factory()->for($place)->create([
            'captured_date' => '2026-03-01',
            'source' => RatingSnapshot::SOURCE_SERPAPI,
        ]);

        $this->expectException(QueryException::class);

        RatingSnapshot::factory()->for($place)->create([
            'captured_date' => '2026-03-01',
            'source' => RatingSnapshot::SOURCE_SERPAPI,
        ]);
    }
}
