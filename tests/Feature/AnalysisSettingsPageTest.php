<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\AnalysisSettings;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalysisSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_access_analysis_settings(): void
    {
        $this->actingAs(User::factory()->developer()->create())
            ->get(AnalysisSettings::getUrl())
            ->assertOk()
            ->assertSee('Pengaturan Analisis')
            ->assertSee('Kuota hari ini');
    }

    public function test_viewer_cannot_access_analysis_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_USER]))
            ->get(AnalysisSettings::getUrl())
            ->assertForbidden();
    }

    public function test_developer_can_toggle_place_mode_off(): void
    {
        $place = Place::factory()->create(['analysis_mode' => Place::MODE_MANUAL]);

        Livewire::actingAs(User::factory()->developer()->create())
            ->test(AnalysisSettings::class)
            ->callTableAction('off', $place);

        $this->assertSame(Place::MODE_OFF, $place->fresh()->analysis_mode);
    }
}
