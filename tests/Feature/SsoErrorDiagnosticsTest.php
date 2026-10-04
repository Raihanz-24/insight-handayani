<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Services\Sso\SsoException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Diagnostik SSO: memastikan penyebab kegagalan JELAS.
 *
 * - exchange_failed mencatat `reason` (kode error Portal) + status, tanpa secret.
 * - Halaman login menampilkan pesan error SSO DI DALAM popup.
 */
class SsoErrorDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        config()->set('sso.enabled', true);
        config()->set('sso.portal_base_url', 'https://portal.test');
        config()->set('sso.client_id', 'cid');
        config()->set('sso.client_secret', 'secret');
    }

    public function test_exchange_failure_logs_safe_reason(): void
    {
        Log::spy();

        Http::fake([
            'portal.test/oauth/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Client tidak dikenal.',
            ], 401),
        ]);

        $this->withSession([
            'sso.state' => 'state-123',
            'sso.code_verifier' => 'verifier-123',
            'sso.started_at' => now()->timestamp,
        ]);

        $this->get('/sso/callback?code=abc&state=state-123')
            ->assertRedirect(route('filament.admin.auth.login'));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $event, array $ctx): bool => $event === 'sso.callback.exchange_failed'
                && ($ctx['reason'] ?? null) === 'invalid_client'
                && ($ctx['status'] ?? null) === 401)
            ->once();
    }

    public function test_sso_error_message_shown_inside_popup(): void
    {
        $this->withSession(['sso_error' => 'Akun Anda belum ditautkan ke aplikasi ini.'])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('ha-sso-gate__error', false)
            ->assertSee('Akun Anda belum ditautkan ke aplikasi ini.');
    }

    public function test_sso_exception_user_messages_are_specific(): void
    {
        $this->assertStringContainsString(
            'client_id/client_secret',
            (new SsoException('x', 'invalid_client'))->userMessage(),
        );

        $this->assertStringContainsString(
            'Redirect URI',
            (new SsoException('x', 'redirect_uri_mismatch'))->userMessage(),
        );

        $this->assertStringContainsString(
            'akses',
            (new SsoException('x', 'access_denied'))->userMessage(),
        );
    }

    public function test_state_mismatch_message_mentions_cookie(): void
    {
        $this->withSession([
            'sso.state' => 'expected',
            'sso.code_verifier' => 'verifier',
            'sso.started_at' => now()->timestamp,
        ]);

        $this->get('/sso/callback?code=abc&state=WRONG')
            ->assertSessionHas('sso_error', fn (string $m): bool => str_contains($m, 'cookie'));
    }

    public function test_login_page_renders_when_sso_off(): void
    {
        config()->set('sso.enabled', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('class="ha-sso-gate"', false);

        $this->assertTrue(class_exists(Login::class));
    }
}
