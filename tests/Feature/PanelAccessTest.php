<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\GuestStatistics;
use App\Filament\Pages\RatingStatistics;
use App\Filament\Resources\GuestEntryResource;
use App\Filament\Resources\PlaceResource;
use App\Filament\Resources\ReviewResource;
use App\Filament\Resources\UserResource;
use App\Models\Place;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->developer()->create();
    }

    private function viewer(): User
    {
        return User::factory()->create(['role' => User::ROLE_USER]);
    }

    // -----------------------------------------------------------------
    // Resource visibility per role
    // -----------------------------------------------------------------

    public function test_only_developer_can_see_place_and_user_resources(): void
    {
        $this->actingAs($this->developer());
        $this->assertTrue(PlaceResource::canViewAny());
        $this->assertTrue(UserResource::canViewAny());

        $this->actingAs($this->viewer());
        $this->assertFalse(PlaceResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());
    }

    public function test_both_roles_can_see_guest_and_review_resources(): void
    {
        foreach ([$this->developer(), $this->viewer()] as $user) {
            $this->actingAs($user);
            $this->assertTrue(GuestEntryResource::canViewAny());
            $this->assertTrue(ReviewResource::canViewAny());
        }
    }

    // -----------------------------------------------------------------
    // Halaman statistik dapat diakses kedua role
    // -----------------------------------------------------------------

    public function test_rating_statistics_page_loads_for_both_roles(): void
    {
        Place::factory()->create(['name' => 'RM Uji']);

        foreach ([$this->developer(), $this->viewer()] as $user) {
            $this->actingAs($user)
                ->get(RatingStatistics::getUrl())
                ->assertOk()
                ->assertSee('Statistik Rating');
        }
    }

    public function test_guest_statistics_page_loads_for_both_roles(): void
    {
        foreach ([$this->developer(), $this->viewer()] as $user) {
            $this->actingAs($user)
                ->get(GuestStatistics::getUrl())
                ->assertOk()
                ->assertSee('Statistik Kendaraan');
        }
    }

    // -----------------------------------------------------------------
    // Panel access (FilamentUser)
    // -----------------------------------------------------------------

    public function test_only_valid_roles_can_access_panel(): void
    {
        $this->actingAs($this->developer())->get('/admin')->assertOk();
        $this->actingAs($this->viewer())->get('/admin')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'ghost']))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_viewer_cannot_open_place_resource_page(): void
    {
        $this->actingAs($this->viewer())
            ->get(PlaceResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_distribution_rendered_on_statistics_page(): void
    {
        $dev = $this->developer();
        $place = Place::factory()->create(['name' => 'Cottage Uji']);

        Review::factory()->for($place)->stars(5)->onDate(now()->toDateString())->count(3)->create();

        $this->actingAs($dev)
            ->get(RatingStatistics::getUrl())
            ->assertOk()
            ->assertSee('Cottage Uji')
            ->assertSee('Distribusi Bintang');
    }
}
