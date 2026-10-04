<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GuestEntry;
use App\Models\Place;
use App\Models\RatingSnapshot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DomainModelsTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // User / role
    // -----------------------------------------------------------------

    public function test_user_role_helpers(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->assertTrue($dev->isDeveloper());
        $this->assertFalse($dev->isViewer());
        $this->assertTrue($user->isViewer());
        $this->assertFalse($user->isDeveloper());
        $this->assertSame('Developer', $dev->roleLabel());
        $this->assertSame('User', $user->roleLabel());
    }

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

        RatingSnapshot::factory()->for($place)->error()->create([
            'captured_at' => now(),
            'captured_date' => now()->toDateString(),
        ]);

        $latest = $place->latestSnapshot();

        $this->assertNotNull($latest);
        $this->assertSame(4.0, $latest->rating);
    }

    // -----------------------------------------------------------------
    // Place — mode analisis & jadwal
    // -----------------------------------------------------------------

    public function test_place_analysis_enabled_depends_on_active_and_mode(): void
    {
        $manual = Place::factory()->create(['analysis_mode' => Place::MODE_MANUAL]);
        $off = Place::factory()->off()->create();
        $inactive = Place::factory()->create(['is_active' => false, 'analysis_mode' => Place::MODE_MANUAL]);

        $this->assertTrue($manual->isAnalysisEnabled());
        $this->assertFalse($off->isAnalysisEnabled());
        $this->assertFalse($inactive->isAnalysisEnabled());
    }

    public function test_place_is_scheduled_requires_mode_and_interval(): void
    {
        $scheduled = Place::factory()->scheduled(1)->create();
        $manual = Place::factory()->create(['analysis_mode' => Place::MODE_MANUAL]);
        $noInterval = Place::factory()->create([
            'analysis_mode' => Place::MODE_SCHEDULED,
            'schedule_interval_days' => null,
        ]);

        $this->assertTrue($scheduled->isScheduled());
        $this->assertFalse($manual->isScheduled());
        $this->assertFalse($noInterval->isScheduled());

        $this->assertSame(1, Place::query()->scheduled()->count());
    }

    public function test_place_is_due_for_sync(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 03:00:00', 'Asia/Jakarta'));

        // Belum pernah sync -> due.
        $fresh = Place::factory()->scheduled(1)->create(['last_synced_at' => null, 'schedule_hour' => 2]);
        $this->assertTrue($fresh->isDueForSync());

        // Baru saja sync, interval 7 hari -> belum due.
        $recent = Place::factory()->scheduled(7)->create([
            'last_synced_at' => Carbon::parse('2026-06-09 02:00:00', 'Asia/Jakarta'),
            'schedule_hour' => 2,
        ]);
        $this->assertFalse($recent->isDueForSync());

        // Sudah lewat interval (8 hari lalu, interval 7) -> due.
        $overdue = Place::factory()->scheduled(7)->create([
            'last_synced_at' => Carbon::parse('2026-06-01 02:00:00', 'Asia/Jakarta'),
            'schedule_hour' => 2,
        ]);
        $this->assertTrue($overdue->isDueForSync());

        // Mode OFF -> tidak pernah due.
        $off = Place::factory()->off()->create(['last_synced_at' => null]);
        $this->assertFalse($off->isDueForSync());

        Carbon::setTestNow();
    }

    // -----------------------------------------------------------------
    // GuestEntry — normalisasi minggu
    // -----------------------------------------------------------------

    public function test_guest_entry_normalizes_week_start_to_monday(): void
    {
        $entry = new GuestEntry;
        $entry->setWeekFromDate('2026-01-07'); // Rabu

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

        $this->assertSame(1, GuestEntry::query()->betweenDates('2026-01-01', '2026-01-31')->count());
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
