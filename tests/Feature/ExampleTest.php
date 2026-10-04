<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman depan mengarahkan ke panel admin (bukan 200 langsung).
     */
    public function test_the_application_redirects_root_to_admin_panel(): void
    {
        $path = trim((string) config('analytics.admin_path', 'admin'), '/');

        $this->get('/')->assertRedirect('/'.$path);
    }
}
