<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso.enabled', true);
        config()->set('sso.portal_base_url', 'https://portal.test');
        config()->set('sso.client_id', 'cid');
        config()->set('sso.client_secret', 'secret');
    }

    public function test_sso_routes_are_404_when_disabled(): void
    {
        config()->set('sso.enabled', false);

        $this->get('/sso/login')->assertNotFound();
        $this->get('/sso/callback')->assertNotFound();
    }

    public function test_login_redirects_to_portal_authorize(): void
    {
        $response = $this->get('/sso/login');

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://portal.test/oauth/authorize', $location);
        $this->assertStringContainsString('client_id=cid', $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);
    }

    public function test_callback_logs_in_linked_user(): void
    {
        $user = User::factory()->developer()->create(['portal_uuid' => 'uuid-linked']);

        Http::fake([
            'portal.test/oauth/token' => Http::response([
                'portal_uuid' => 'uuid-linked',
                'email' => $user->email,
                'status' => 'active',
            ], 200),
        ]);

        // Siapkan session state seperti hasil /sso/login.
        $this->withSession([
            'sso.state' => 'state-123',
            'sso.code_verifier' => 'verifier-123',
            'sso.started_at' => now()->timestamp,
        ]);

        $this->get('/sso/callback?code=abc&state=state-123')
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_rejects_unknown_uuid_without_autocreate(): void
    {
        Http::fake([
            'portal.test/oauth/token' => Http::response([
                'portal_uuid' => 'nobody-knows',
                'email' => 'x@example.com',
                'status' => 'active',
            ], 200),
        ]);

        $this->withSession([
            'sso.state' => 'state-123',
            'sso.code_verifier' => 'verifier-123',
            'sso.started_at' => now()->timestamp,
        ]);

        $this->get('/sso/callback?code=abc&state=state-123')
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
        $this->assertSame(0, User::query()->where('portal_uuid', 'nobody-knows')->count());
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $this->withSession([
            'sso.state' => 'expected-state',
            'sso.code_verifier' => 'verifier-123',
            'sso.started_at' => now()->timestamp,
        ]);

        $this->get('/sso/callback?code=abc&state=WRONG')
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    public function test_callback_rejects_expired_state(): void
    {
        Http::fake();

        $this->withSession([
            'sso.state' => 'state-123',
            'sso.code_verifier' => 'verifier-123',
            'sso.started_at' => time() - 100000,
        ]);

        $this->get('/sso/callback?code=abc&state=state-123')
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }
}
