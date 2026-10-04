<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SSO GATE pada halaman login Insight.
 *
 * - SSO aktif (SSO_ENABLED=true) → halaman login DITUTUP overlay (pop-up
 *   terkunci) dengan tombol menuju Portal + login langsung DITOLAK server-side.
 * - SSO nonaktif → login langsung tetap terbuka (tanpa overlay).
 */
class SsoLoginGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_login_page_is_gated_when_sso_enabled(): void
    {
        config()->set('sso.enabled', true);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('ha-sso-gate', false)
            ->assertSee('Masuk melalui Portal Handayani')
            ->assertSee('Login dengan Portal Handayani')
            ->assertSee(route('sso.login'), false);
    }

    public function test_login_page_is_open_when_sso_disabled(): void
    {
        config()->set('sso.enabled', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('class="ha-sso-gate"', false)
            ->assertDontSee('Masuk melalui Portal Handayani')
            // Form login normal tetap ada (tombol "Masuk").
            ->assertSee('Masuk');
    }

    public function test_direct_login_is_rejected_server_side_when_sso_enabled(): void
    {
        config()->set('sso.enabled', true);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('secure-password'),
        ]);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'secure-password')
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_direct_login_works_when_sso_disabled(): void
    {
        config()->set('sso.enabled', false);

        $user = User::factory()->developer()->create([
            'email' => 'admin2@example.com',
            'password' => Hash::make('secure-password'),
        ]);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'secure-password')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
    }
}
