<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Responses\Auth\LogoutResponse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SsoLogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_logout_redirects_to_portal_when_sso_enabled(): void
    {
        config()->set('sso.enabled', true);
        config()->set('sso.portal_base_url', 'https://portal.test');

        $response = (new LogoutResponse)->toResponse(Request::create('/admin/logout', 'POST'));

        $this->assertSame('https://portal.test', $response->getTargetUrl());
    }

    public function test_logout_redirects_to_login_when_sso_disabled(): void
    {
        config()->set('sso.enabled', false);

        $response = (new LogoutResponse)->toResponse(Request::create('/admin/logout', 'POST'));

        $this->assertSame(route('filament.admin.auth.login'), $response->getTargetUrl());
    }

    public function test_root_redirects_to_admin_path(): void
    {
        config()->set('analytics.admin_path', 'hndy-rahasia');

        $this->get('/')->assertRedirect('/hndy-rahasia');
    }

    public function test_authenticated_user_sees_panel_after_root_redirect(): void
    {
        $this->actingAs(User::factory()->developer()->create())
            ->get('/admin')
            ->assertOk();
    }
}
